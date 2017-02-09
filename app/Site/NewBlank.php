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

class NewBlank extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Setup new magento2 site with sample data.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name% [-f] [-m version] <Name> [Version]</info>
  
  Options:
	-f | --force		No confirm y/N
	-m | --version-number	Version magento
  
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
		$output->writeln('Installing magento2 '. $input->getOption('version-number'));
		//check HTDOCS config value
		$_htdocs = \Config::get('HTDOCS');
		if(!\Config::get('HTDOCS')){
			$output->writeln('The value of variable HTDOCS undefined in config.ini');
			return;
		}
		$_htdocs = rtrim($_htdocs, '\/\\');
		
		//ask version command input
		$version = $input->getOption('version-number');
		if($input->getArgument('Version')){
			$version = $input->getArgument('Version');
		}
		if(!$version){
			exec('php '. PROG_FILE . ' magento:versions -f', $exec_output);
			if(isset($exec_output[0])){
				$version = $exec_output[0];
				$output->writeln('The lastest version is '.$version);
			}
			if(!$version){
				$output->writeln('Can not find magento2 version.');
				return;
			}
		}
		
		//check name
		//if(!$input->getArgument('Name')){
		//	$output->writeln('Argument <Name> in <info>php '.$_SERVER['PHP_SELF'].' '.$this->getName().' <Name></info> can not be empty.');
		//	return;
		//}
		
		//SOURCE_CODE formated
		$SOURCE_CODE = trim(\Config::get('SOURCE_CODE'), '.\/\\');
		//to process all
		
		//check magento source exists
		if(!file_exists(BP.'/'.$SOURCE_CODE.'/'.$version.'/')){
			$output->writeln('Downloading magento '.$version.' from github.com');
			exec('php '.PROG_FILE.' magento:download '.$version);
		}
		
		//get last_name to append to prefix name in config
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
		$db_host = \Config::get('MYSQL_HOST');
		$db_name = substr(\Config::get('MYSQL_DBNAME_PREFIX'), 0, 9).$last_db_name;
		$db_user = substr(\Config::get('MYSQL_USER_PREFIX'), 0, 9).$mid_name.$last_name;
		$db_pass = substr(sha1(rand(10000000000, 99999999999).microtime()), 0, 12); //auto generate password
		
		// prepare database user and password
		// check database
		if(\Helper::get('database')->checkUserExists($db_user)){
			//$output->writeln('The username '.$db_user.' already existed please choose difference name.');
			//return;
		}
		// check username
		if(\Helper::get('database')->checkDbExists($db_name)){
			//$output->writeln('The database '.$db_name.' already existed please choose difference name.');
			//return;
		}
		
		// check destination exists and override
		if(file_exists($_htdocs.'/'.$input->getArgument('Name').'/')){
			$copy_allowed = false;
			if($input->getOption('force')){
				$copy_allowed = true;
			}else{
				$output->writeln('Destination '.$_htdocs.'/'.$input->getArgument('Name').' already existed.');
				$confirm = new ConfirmationQuestion('Do you continue and override? (y/N)(Default Yes): ', false);
				if($questionHelper->ask($input, $output, $confirm)){
					if($_htdocs) exec('rm -rf '.$_htdocs.'/'.$input->getArgument('Name').'/');
					$copy_allowed = true;
				}
			}
		}else{
			$copy_allowed = true;
		}
		
		// begin copying magento source
		$SITE_PATH = $_htdocs.'/'.$input->getArgument('Name');
		if($copy_allowed){
			$output->writeln('Copying source from '.BP.'/'.$SOURCE_CODE.'/'.$version.'/ to '.$_htdocs.'/'.$input->getArgument('Name').'/');
			if(!file_exists($SITE_PATH.'/'))
				exec('mkdir '.$SITE_PATH);
			exec('cp -R '.BP.'/'.$SOURCE_CODE.'/'.$version.'/* '.$SITE_PATH.'/', $exec_output); //copy source to disk
			$output->writeln('Copy source code was completed.');
		}
		
		/* prepare database user and password */
		// create database
		exec('php '.PROG_FILE.' database:create '.$db_name);
		// create username
		if($db_pass == '')
			$db_pass = substr(sha1(rand(10000000000, 99999999999).microtime()), 0, 12); //auto generate password
		exec('php '.PROG_FILE.' database:create:user --password='.$db_pass.' '.$db_user.' '.$db_name);
		
		
		//$output->writeln('Config setting site...');
		//exec('cd '.$SITE_PATH.' ; php bin/magento setup:config:set --backend-frontname="admin" --db-host="'.$db_host.'" --db-name="'.$db_name.'" --db-user="'.$db_user.'" --db-password="'.$db_pass.'"', 
		//	$exec_output);
		
		$backend_name = 'admin';
		if(\Config::get('BACKEND_NAME')) $backend_name = \Config::get('BACKEND_NAME');
		
		$output->writeln('Installing site...');
		exec('cd '.$SITE_PATH.' ; php bin/magento setup:install \
		--backend-frontname="'.$backend_name.'" --db-host="'.$db_host.'" --db-name="'.$db_name.'" --db-user="'.$db_user.'" --db-password="'.$db_pass.'" \
		--base-url="'.rtrim(\Config::get('BASE_URL'), '\/').'/'.$input->getArgument('Name').'/" \
		--admin-firstname=Magento --admin-lastname=User --admin-email=admin@magestore.com \
		--admin-user=admin --admin-password=admin123 --language=en_US \
		--currency=USD --timezone=America/Chicago --use-sample-data', $exec_output);
		
		$output->writeln('Upgrading site...');
		unset($exec_output);
		exec('cd '.$SITE_PATH.' ; php bin/magento setup:upgrade', $exec_output); //upgrade database
		$line_out = '';
		foreach($exec_output as $l_out){
			$line_out .= $l_out;
		}
		$output->writeln($line_out);

		//exec('cd '.$_htdocs.'/'.$input->getArgument('Name').' ; php bin/magento sampledata:reset', $exec_output);
		//exec('cd '.$_htdocs.'/'.$input->getArgument('Name').' ; php bin/magento sampledata:deploy', $exec_output);
		$output->writeln('Set permission and owner...');
		exec('cd '.$SITE_PATH.' ; chown -R '.\Config::get('HTDOCS_USER').':'.\Config::get('HTDOCS_GROUP').' .', $exec_output);
		exec('cd '.$SITE_PATH.' ; chmod -R g+rw .', $exec_output);
		
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
		
		$output->writeln('Setup was complated please copy url <info>'.rtrim(\Config::get('BASE_URL'), '\/').'/'.$input->getArgument('Name').'/</info> and paste to the web browser to install magento site.');
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('Name', InputArgument::REQUIRED, 'The name of site with url path http://demo.example.com/<Name>/'),
			new InputArgument('Version', InputArgument::OPTIONAL, 'Version in available magento version list.'),
			new InputOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Fource to install and overwide if website name already existed.', 0),
			new InputOption('version-number', 'm', InputOption::VALUE_OPTIONAL, 'Version in available magento version list.', null),
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

