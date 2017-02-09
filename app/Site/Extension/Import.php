<?php
/**
* Download magento source code by version
*/

namespace App\Site\Extension;

use Symfony\Component\Console\Command\Command as Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Import extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Import extensions on github')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name% [URL]</info>
  
EOF
            )
        ;
    }
	
	
	/**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$url = $input->getArgument('URL');
		if(\Config::get('EXTENSION_DIR')){
			$extdir = rtrim(\Config::get('EXTENSION_DIR'), '\/\\');
		}else{
			$extdir = 'extensions';
		}
		$output->writeln('Processing...');
		// check folder
		if(!file_exists(BP.'/'.$extdir.'/')){
			exec('mkdir '.BP.'/'.$extdir.'/ >/dev/null 2>&1');
		}
		// get name
		$ext_name = basename($url, '.git');
		if(file_exists(BP.'/'.$extdir.'/'.$ext_name.'/')){
			exec('rm -rf '.BP.'/'.$extdir.'/'.$ext_name);
		}
		exec('cd '.BP.'/'.$extdir.' ; git clone '.$url.' '.BP.'/'.$extdir.'/'.$ext_name, $exec_output); //clone to specific folder
		// get version tag
		exec('cd '.BP.'/'.$extdir.'/'.$ext_name.' ; git tag | sort -rn', $versions);
		$lastest = reset($versions);
		// checkout to lastest version
		exec('cd '.BP.'/'.$extdir.'/'.$ext_name.' ; git checkout '.$lastest, $exec_output);
		// save to setting
		\App\Setting::set('/extensions/'.$ext_name.'/'.$lastest.'/', $url);
		$output->writeln('Done!');
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('URL', InputArgument::REQUIRED, 'The github repository url. You should use ssh url git@github.com:VENDOR/NAME.git')
        ));
    }
}

