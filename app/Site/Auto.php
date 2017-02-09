<?php
/**
* Download magento source code by version
*/

namespace App\Magento;

use Symfony\Component\Console\Command\Command as Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Download extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Download magento2 from github commands')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name% [version]</info>
  
EOF
            )
        ;
    }
	
	
	/**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$questionHelper = $this->getHelperSet()->get('question');
		$output->writeln('Cloning magento2 version '. $input->getArgument('version') .' from github.com/magento/magento2');
		
		if(!\Config::get('SOURCE_GIT')){
			$output->writeln('The value of variable SOURCE_GIT undefined in config.ini');
			$output->writeln('Default is magento2/develop');
			
			$questionHelper = $this->getHelperSet()->get('question');
			$question = new ConfirmationQuestion('Are you want to continue? (y/N):', true);
			
			if(!$questionHelper->ask($input, $output, $question)){
				return;
			}
			
			\Config::set('SOURCE_GIT', '/magento2/develop');
		}
		
		//SOURCE_CODE formated
		$SOURCE_CODE = trim(\Config::get('SOURCE_CODE'), '.\/\\');
		//format $SOURCE_GIT
		$SOURCE_GIT = trim(\Config::get('SOURCE_GIT'), '.\/\\');
		
		//check source code folder
		if(!file_exists(BP.'/'.$SOURCE_CODE)){
			exec('mkdir '.BP.'/'.$SOURCE_CODE.' >/dev/null 2>&1');
		}
		//check magento2 source git exist
		exec('cd '. BP . '/' . $SOURCE_GIT.' >/dev/null 2>&1', $cd_output, $error);
		if($error == 2 || !file_exists(BP.'/'.$SOURCE_GIT.'/.git')){
			//clone new magento2 source code to the disk
			$output->writeln('-----------------------------------');
			$output->writeln('Magento2 source code does not exist.');
			$question = new Question('Enter url of git repository to clone (Default https://github.com/magento/magento2.git): ', 'https://github.com/magento/magento2.git');
			//set callback function for question
			$question->setNormalizer(function ($answer) {
				return $answer; //return to ask function
			});
			//get answer git url
			if(($answer = $questionHelper->ask($input, $output, $question)) != ''){
				if($answer !== ''){
					exec('cd '.BP.'/'.$SOURCE_CODE.' ; git clone '.$answer.' '.BP.'/'.$SOURCE_GIT, $exec_output); //clone to specific folder
				}else{
					$output->writeln('Url is empty. Exit program!');
					return;
				}
			}
		}
		//check magento2 source git after cloned
		if(!file_exists(BP.'/'.$SOURCE_GIT.'/.git')){
			$output->writeln('Not found '.BP.'/'.$SOURCE_GIT.'/.git');
			return;
		}
		
		$version = $input->getArgument('version');
		if(!$version && $version == ''){
			unset($exec_output); //clear output console
			//get the first version
			exec('php '. PROG_FILE . ' magento:versions -f', $exec_output);
			if(!isset($exec_output[0]) || $exec_output[0] == ''){
				$output->writeln('Cannot find version number from git repository! Proccess ended.');
				return;
			}
			$version = $exec_output[0];
		}
		//validate version required
		if(!$version){
			$output->writeln('Version number of magento2 not defined (Null value).');
			return;
		}
		
		//copy source to folder name
		$copy_to = $version;
		
		//ask for if existing directory of magento version in disk
		if(file_exists(BP.'/'.$SOURCE_CODE.'/'.$copy_to)){
			do{
				$quit = false; //flag to quit
				$question = new ConfirmationQuestion('The directory '. BP.'/'.$SOURCE_CODE.'/'.$version .' existing in disk. Do you want to override? (y/N): ', false);
				$question->setAutocompleterValues(array('N'));
				if(!$questionHelper->ask($input, $output, $question)){
					$question = new Question('Please type the different name: ');
					//set callback function for question
					$question->setNormalizer(function ($answer) {
						return $answer; //return to ask function
					});
					//if answer no and typing a new name
					if(($answer = $questionHelper->ask($input, $output, $question)) != ''){
						$copy_to = $answer;
						//check folder still existed
						if(file_exists(BP.'/'.$SOURCE_CODE.'/'.$copy_to)){
							//continue to check and ask user
							$question = new ConfirmationQuestion('The name '.$answer.' was existed in the disk. Do you want to override? (y/N): ', true);
							if($questionHelper->ask($input, $output, $question)){
								$quit = true; //quit when user use this name to override
							}
						}else{
							$quit = true; //quit to proccessing
						}
					}else{
						$output->writeln('The name must be not null.');
						//ask to quit
						$question = new ConfirmationQuestion('Do you want to quit? (y/N): ', false);
						if($questionHelper->ask($input, $output, $question)){
							$quit = true;
							return;
						}
					}
				}else{
					break; //break loop
					//$quit = true;
				}
			}while(!$quit);
		}
		
		unset($exec_output); //clear output
		$output->writeln('Git checkout and pulling source code ...');
		//check source magento folder exist .git
		if(!file_exists(BP.'/'.$SOURCE_GIT.'/.git')){
			$output->writeln('Error with magento2 source code git not found.');
			$output->writeln('Command ended!');
			return;
		}
		exec('cd '.BP.'/'.$SOURCE_GIT.' ; git checkout '.$version.' ; git pull origin '.$version, $exec_output);
		$output->writeln('Copying source code to folder '.$copy_to.' ...');
		exec('cp -R '. BP.'/'.$SOURCE_GIT.'/ '. BP.'/'.$SOURCE_CODE.'/'.$copy_to.'/', $exec_output);
		exec('rm -rf '. BP.'/'.$SOURCE_CODE.'/'.$copy_to.'/.git', $exec_output); //remove .git folder
		
		$output->writeln('Complete!');
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('version', InputArgument::OPTIONAL, 'The version number etc 2.1.1'),
			//new InputOption('opt3', null, InputOption::VALUE_REQUIRED, 'Option 3', '3'),
        ));
    }
}

