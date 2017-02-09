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
		//$questionHelper = $this->getHelperSet()->get('question');
		
		if(!\Config::get('SOURCE_GIT')){
			$output->writeln('The value of variable SOURCE_GIT undefined in config.ini');
			$output->writeln('Default is magento2/develop');
			\Config::set('SOURCE_GIT', '/magento2/develop');
		}
		
		// Get config values
		$SOURCE_DIR = trim(\Config::get('SOURCE_DIR'), '.\/\\');
		//$SOURCE_GIT = trim(\Config::get('SOURCE_GIT'), '.\/\\');
		
		//check source code folder
		if(!file_exists(BP.'/'.$SOURCE_DIR)){
			exec('mkdir '.BP.'/'.$SOURCE_DIR.' >/dev/null 2>&1');
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
		$MAGENTO_SRC = BP.'/'.$SOURCE_DIR.'/'.$copy_to;
		$repoUrl = \App\Setting::get('repo');
		
		//ask for if existing directory of magento version in disk
		if(file_exists( $MAGENTO_SRC )){
			exec('rm -rf '.$MAGENTO_SRC); //remove old folder
		} else {
			exec('mkdir -p '. $MAGENTO_SRC); //create folder
		}
		$output->writeln('Git checkout source code ...');
		exec('git clone --branch '.$version.' '.$repoUrl.' '.$MAGENTO_SRC, $exec_output);

		unset($exec_output); //clear output
		
		// configure and install magento2 via composer
		// setting composer repositories
		exec('cd '.$MAGENTO_SRC.' ; composer config repositories.magento composer https://repo.magento.com 2>/dev/null', $exec_output); //copy source to disk
		$composer_file = $MAGENTO_SRC.'/composer.json';
		if(!file_exists($composer_file)){
			$output->writeln('Composer file not found in '.$MAGENTO_SRC.'/');
			return;
		}
		
		// update composer file contents
		\App\Setting::updateComposer($composer_file); //update require sampledata module of composer file

		// run composer update
		$output->writeln('Update composer...');
		exec('cd '.$MAGENTO_SRC.' ; composer update', $exec_output);
		
		$output->writeln('Done!');
		
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

