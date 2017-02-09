<?php
/**
* Download magento source code by version
*/

namespace App\Site;

use Symfony\Component\Console\Command\Command as Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;

class CloneSite extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Clone new magento2 site with installed site.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name% [-f] <Name> [Version]</info>
  Options:
	-f | --force 			No confirm y/N
  
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
		
		//check HTDOCS config value
		$_htdocs = rtrim(\Config::get('HTDOCS'), '\/\\');
		if(!\Config::get('HTDOCS')){
			$output->writeln('The HTDOCS value is null.');
			return;
		}
		// installed dir
		$INSTALLED_DIR = BP.'/'.rtrim(\Config::get('INSTALLED_DIR'), '\/\\');
		if(!\Config::get('INSTALLED_DIR')){
			$INSTALLED_DIR = BP.'/installed';
		}
		
		//ask version command input
		$version = $input->getArgument('Version');
		if(!$version){
			$output->writeln('Please choose magento2 version: ');
			exec('cd '.$INSTALLED_DIR.'; ls | sort -r', $ls_output);
			$magento2_version = '';
			foreach($ls_output as $line){
				$magento2_version .= ' '.$line;
			}
			$output->writeln($magento2_version);
			$ls_output = explode(' ', str_replace('  ', ' ', trim($magento2_version)));
			
			$ask_input = new Question(': ');
			$ask_input->setAutocompleterValues($ls_output);
			$version = $this->askInput($input, $output, $ask_input, 'Version', 1);
			//if($version === -1){
			//	return; //quit
			//}
			
			
			if(isset($ls_output[0])){
				$version = $ls_output[0];
				$output->writeln('Auto chosen latest version '.$version);
			}
			if(!$version){
				$output->writeln('Can not find magento2 version.');
				return;
			}
		}
		
		
		//check name
		if(!$input->getArgument('Name')){
			$output->writeln('Argument <name> in <info>php '.$_SERVER['PHP_SELF'].' '.$this->getName().' <Name></info> can not be empty.');
			return;
		}
		
		// choose version to clone
		if(!file_exists($INSTALLED_DIR.'/'.$version.'/')){
			$output->writeln('Version '.$version.' doesn\'t exists (in '.$INSTALLED_DIR.'/'.$version.'/'.').');
			return;
		}
		
		//get last_name to append to prefix name in config
		$last_name = str_replace(array('/', '-', '.'), array('', '', ''), $input->getArgument('Name'));
		$mid_name = substr($last_name, (int) strlen($last_name)/3*2-3, 3); //limit string to 6 charactor
		$last_name = substr($last_name, -3); //limit string to 6 charactor
		// get last db_name
		$last_db_name = str_replace(array('/', '-', '_', '.'), array('', '', '', ''), $input->getArgument('Name'));
		if(strlen($last_db_name) > 51){
			$last_db_name = substr($last_db_name, 0, 40).rand(10000000000, 99999999999);
		}
		
		// database infomations
		$db_name = substr(\Config::get('MYSQL_DBNAME_PREFIX'), 0, 9).$last_db_name;
		$db_user = substr(\Config::get('MYSQL_USER_PREFIX'), 0, 9).$mid_name.$last_name;
		//$db_pass = \Config::get('MYSQL_PASS');
		$db_pass = substr(sha1(rand(10000000000, 99999999999).microtime()), 0, 12); //auto generate password
		
		///*-----------------*/
		
		// check dir to install to
		if(!$input->getOption('force') && file_exists($_htdocs.'/'.$input->getArgument('Name').'/')){
			$output->writeln('The dir '.$_htdocs.'/'.$input->getArgument('Name').' already existed. Please remove or choose a new name.');
			
			// ask remove destination dir
			$choice = array('No. I want to choose new name.', 'Yes. Remove the old dir.');
			$question = new ChoiceQuestion('Are you want to remove ?', $choice, 0);
			if($questionHelper->ask($input, $output, $question) == $choice[1]){
				$output->writeln('Removing...');
				exec('rm -rf '.$_htdocs.'/'.$input->getArgument('Name').'/');
			}else{
				return;
			}
		}
		
		// begining copy from installed source to install dir
		$output->writeln('Copying source from '.$INSTALLED_DIR.'/'.$version.'/ to '.$_htdocs.'/'.$input->getArgument('Name').'/');
		exec('cp -R '.$INSTALLED_DIR.'/'.$version.'/ '.$_htdocs.'/'.$input->getArgument('Name').'/');
		//$output->writeln('Copy complete.');
		
		// change config informations of site
		$db_info = \Helper::get('Magento')->getDatabaseInfo($_htdocs.'/'.$input->getArgument('Name'));
		if(!isset($db_info['host']) || !isset($db_info['dbname']) || !isset($db_info['username'])){
			$output->writeln('Empty database infomations.');
			return;
		}
		
		// copy database
		$output->writeln('Copying database... '.$db_info['dbname'].' to '.$db_name);
		$rest = exec('php '.PROG_FILE.' database:copy '.$db_info['dbname'].' '.$db_name); //copy
		if($rest != 'Done!'){
			$output->writeln('Cannot copy/clone a non existed database '.$db_info['dbname'] . '.');
			return;
		}
		exec('php '.PROG_FILE.' database:create:user -f --password="'.$db_pass.'" '.$db_user.' '.$db_name, $exec_out); //grant user to database
		
		// backup config file and rewrite it
		exec('cp '.$_htdocs.'/'.$input->getArgument('Name').'/app/etc/env.php '.$_htdocs.'/'.$input->getArgument('Name').'/app/etc/env.php.bakclone');
		// rewrite config file
		\Helper::get('Magento')->changeDatabaseInfo($_htdocs.'/'.$input->getArgument('Name'), array('dbname'=>$db_name, 'username'=>$db_user, 'password'=>$db_pass));
		
		// change base url
		$sql = 'UPDATE \`core_config_data\` SET \`value\` = \''.rtrim(\Config::get('BASE_URL'), '\/').'/'.$input->getArgument('Name').'/\' WHERE \`path\` LIKE \'%base_url%\'';
		\Helper::get('Database')->run($sql, $db_name);
		
		// deploy and clean cache
		$output->writeln('Deploy...');
		exec('cd '.$_htdocs.'/'.$input->getArgument('Name').'/; php bin/magento setup:static-content:deploy');
		$output->writeln('Upgrade database...');
		exec('cd '.$_htdocs.'/'.$input->getArgument('Name').'/; php bin/magento setup:upgrade');
		$output->writeln('Clean cache...');
		exec('cd '.$_htdocs.'/'.$input->getArgument('Name').'/; php bin/magento cache:clean');
		
		// change permission
		$output->writeln('Set permission and owner...');
		exec('cd '.$_htdocs.'/'.$input->getArgument('Name').' ; chown -R '.\Config::get('HTDOCS_USER').':'.\Config::get('HTDOCS_GROUP').' .', $exec_output);
		exec('cd '.$_htdocs.'/'.$input->getArgument('Name').' ; chmod -R g+rw .', $exec_output);
		
		$output_screen = <<<EOF
All informations of site:

  <info>Url:		%url%</info>
  <info>Backend:	%admin_url%</info>
  <info>User:		%user% / %password%</info>
  <info>DB Name: 	%db_name%</info>
  <info>DB User:	%db_user% / %db_password%</info>
  <info>Admin email:	%email%</info>
  
EOF;

		$output_screen = str_replace(array('%url%', '%admin_url%', '%user%', '%password%', '%db_name%', '%db_user%', '%db_password%', '%email%'), 
									 array(rtrim(\Config::get('BASE_URL'), '\/').'/'.$input->getArgument('Name').'/', 
										   rtrim(\Config::get('BASE_URL'), '\/').'/'.$input->getArgument('Name').'/admin',
										'admin', 'admin123', $db_name, $db_user, $db_pass, 'admin@magestore.com'
									 ), $output_screen);
		
		$output->writeln($output_screen);
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('Name', InputArgument::REQUIRED, 'The name of site you going to install as url path http://demo.example.com/<name>/'),
            new InputArgument('Version', InputArgument::OPTIONAL, 'Version magento site to clone.'),
			new InputOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Fource to install and overwide if website name already existed.', 0),
			
        ));
    }
	
	/**
	* ask input value form user with loop limited
	*/
	private function askInput(InputInterface $input, OutputInterface $output, Question $question, $varname = 'input', $limit = 3){
		if($limit <= 0) $limit = 1;
		$questionHelper = $this->getHelperSet()->get('question');
		$count = 0;
		do{
			//set callback function for question
			$question->setNormalizer(function ($answer) {
				return $answer; //return to ask function
			});
			//DO ASK
			$answer = $questionHelper->ask($input, $output, $question);
			$count++;
			if(!$answer && $count > 3){
				$confirm = new ConfirmationQuestion('Are you want to quit? (y/N): ', true);
				if($questionHelper->ask($input, $output, $confirm)){
					$output->writeln('Bye!');
					return -1; //return signal to quit
				}else{
					$count = 1;
				}
			}
			
			if(!$answer){
				$output->writeln('The value of '.$varname.' can not be empty.');
			}
		}while( !$answer && $count <= $limit );
		
		return $answer;
	}
}

