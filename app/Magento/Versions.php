<?php
/**
* Display list all versions ( releases ) in https://github.com/magento/magento2
*/

namespace App\Magento;

use Symfony\Component\Console\Command\Command as Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;


class Versions extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Show all versions of magento2 from github')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name%</info>
  
Use option -l or --line to break line:

  <info>php %command.full_name% -l</info>
Or
  <info>php %command.full_name% --line</info>

Use option -f or --first to get the first version:

  <info>php %command.full_name% [-f|--first]</info>
  
EOF
            )
        ;
    }
	
	
	/**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
		
		//format $SOURCE_GIT
		$SOURCE_GIT = trim(\Config::get('SOURCE_GIT'), '.\/\\');
		
		if(exec('cd '. BP . '/' . $SOURCE_GIT) != ''){
			//$output->writeln('Error can not change to directory '. BP . '/' . $SOURCE_GIT);
			return;
		}
		exec('cd '. BP . '/' . $SOURCE_GIT . ' ; git fetch ; git tag | sort -rn ', $exec_output);
		
		if($input->getOption('first')){
			foreach($exec_output as $line){
				$output->writeln($line);
				break;
			}
			return;
		}
		
		$output->writeln('All versions:');
		if($input->getOption('line')){
			foreach($exec_output as $line){
				$output->writeln($line);
			}
		}else{
			$output->writeln(implode(', ', $exec_output));
		}
		
    }
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            //new InputArgument('version', InputArgument::OPTIONAL, 'The version number etc 2.1.1'),
			new InputOption('line', 'l', InputOption::VALUE_NONE, 'Break line'),
			new InputOption('first', 'f', InputOption::VALUE_NONE, 'Get output of first version'),
        ));
    }
}

