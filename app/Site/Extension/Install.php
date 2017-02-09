<?php
/**
* Intall extension to magento site
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

class Install extends Command
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

		$EXTENSION_DIR = trim(\Config::get('EXTENSION_DIR'), '.\/\\');
		if(!$EXTENSION_DIR){
			$EXTENSION_DIR = 'extensions';
		}
		//check $EXTENSION_DIR folder
		$EXTENSION_DIR_PATH = BP.'/'.$EXTENSION_DIR.'/';
		if(!file_exists($EXTENSION_DIR_PATH)){
			$output->writeln('The dir '.$EXTENSION_DIR_PATH.' doesn\'t exists.');
			return;
		}

		//check extension name folder
		$EXTENSION_URL = $input->getArgument('Name');

		if(!$EXTENSION_URL){
			$output->writeln('That version doesn\'t not exists. All available versions are here:');
			//print all version was imported
			$extensions = \App\Setting::get('extensions');
			foreach($extensions as $name => $vers){
				$ver = '';
				if(is_array($vers)){
					foreach($vers as $vKey => $val){
						$ver .= ' '.$vKey;
					}
				}else{
					$ver = $vers;
				}
				$output->writeln('<info>'.$name.' : '.$ver.'</info>');
			}
			return;
		}

		$EXTENSION_NAME = basename($EXTENSION_URL, '.git'); //get name

		/*if(!file_exists($EXTENSION_DIR_PATH.$EXTENSION_NAME)){
			$output->writeln('The extension dir '.$EXTENSION_DIR_PATH.$EXTENSION_NAME.' doesn\'t exists.');
			return;
		}*/

		// import
		if(!file_exists($EXTENSION_DIR_PATH.$EXTENSION_NAME.'/.git')){
			if(strpos($EXTENSION_URL, 'git@github.com') !== false || strpos($EXTENSION_URL, 'http') !== false){
				exec('php '.PROG_FILE.' site:extension:import '.$EXTENSION_URL, $exec_output);
			}
		}

		//check .git folder
		if(!file_exists($EXTENSION_DIR_PATH.$EXTENSION_NAME.'/.git')){
			$output->writeln('The extension dir '.$EXTENSION_DIR_PATH.$EXTENSION_NAME.' is not a git source. Please checkout it.');
			return;
		}

		//get version number
		$VERSION = $input->getArgument('Version');
		if(!$VERSION || !$input->getArgument('Site-Path')){
			// get version tag
			exec('cd '.$EXTENSION_DIR_PATH.$EXTENSION_NAME.' ; git tag | sort -rn', $versions);
			$VERSION = reset($versions); //get latest version number
		}

		// checkout to by version
		exec('cd '.$EXTENSION_DIR_PATH.$EXTENSION_NAME.' ; git checkout '.$VERSION, $exec_output);

		// get site path
		$SITE_PATH = $input->getArgument('Site-Path');
		if(!$SITE_PATH){
			$SITE_PATH = $input->getArgument('Version');
		}

		if(!$SITE_PATH){
			$output->writeln('Empty path to site.');
			return;
		}

		$SITE_REAL_PATH = $SITE_PATH;
		if(strpos($SITE_PATH, \Config::get('HTDOCS')) == false){
			$SITE_REAL_PATH = rtrim(\Config::get('HTDOCS'), '.\/\\').'/'.rtrim($SITE_PATH, '.\/\\');
		}

		// warning install
		if(rtrim(\Config::get('HTDOCS'), '.\/\\') == rtrim($SITE_REAL_PATH, '.\/\\')){
			$output->writeln('The install path is match to htdocs path '.\Config::get('HTDOCS'));
			if(!$input->getOption('force')){
				$output->writeln('Run command again with option -f');
				return;
			}
		}

		$output->writeln('Copying...');
		exec('cp -R '.$EXTENSION_DIR_PATH.$EXTENSION_NAME.'/* '.$SITE_REAL_PATH.'/', $exec_output);


//		exec('cd '.BP.'/'.$SOURCE_GIT.' ; git checkout '.$version.' ; git pull origin '.$version, $exec_output);

//		exec('rm -rf '. BP.'/'.$SOURCE_CODE.'/'.$copy_to.'/.git', $exec_output); //remove .git folder
		
		$output->writeln('Done!');
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('Name', InputArgument::OPTIONAL, 'Extension name or url, you should use ssh url git@github.com:VENDOR/NAME.git'),
            new InputArgument('Version', InputArgument::OPTIONAL, 'The version number etc 2.1.1'),
            new InputArgument('Site-Path', InputArgument::OPTIONAL, 'Absolute or relative path include htdocs to website'),
			new InputOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Force to install', 'N'),
        ));
    }
}

