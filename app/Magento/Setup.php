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

class Setup extends Command
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
		
		//SOURCE_DIR formated
		$SOURCE_DIR = trim(\Config::get('SOURCE_DIR'), '.\/\\');
		$MAGENTO_SRC = BP.'/'.$SOURCE_DIR.'/'.$version;
		$HTDOCS_PATH = $_htdocs.'/'.$input->getArgument('Name');

		//check magento source exists
		if(!file_exists( $MAGENTO_SRC )){
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
		//if(\Helper::get('database')->checkUserExists($db_user)){
			//$output->writeln('The username '.$db_user.' already existed please choose difference name.');
			//return;
		//}
		// check username
		//if(\Helper::get('database')->checkDbExists($db_name)){
			//$output->writeln('The database '.$db_name.' already existed please choose difference name.');
			//return;
		//}
		
		// check destination exists and override
		if(file_exists( $HTDOCS_PATH ) || $input->getOption('force')){
			if ($input->getOption('force')) {
				exec('rm -rf '. $HTDOCS_PATH);
			} else {
				$output->writeln('Destination '. $HTDOCS_PATH .' already existed.');
				$confirm = new ConfirmationQuestion('Do you continue and override? (y/N)(Default Yes): ', false);
				if($questionHelper->ask($input, $output, $confirm)){
					exec('rm -rf '. $HTDOCS_PATH);
				} else {
					$output->writeln('Exit');
					return;
				}
			}
		}
		
		// begin copying magento source
		if(!file_exists( $HTDOCS_PATH .'/')) {
			exec('mkdir -p '. $HTDOCS_PATH );
		}
		$output->writeln( 'Copying source from '. $MAGENTO_SRC .' to '. $HTDOCS_PATH );
		exec('cp -R '. $MAGENTO_SRC .'/* '. $HTDOCS_PATH .'/', $exec_output); //copy source to disk
		exec('cp '. $MAGENTO_SRC .'/.htaccess '. $HTDOCS_PATH .'/', $exec_output); //copy htaccess file
		//$output->writeln('Copy source code was completed.');
		
		/* prepare database user and password */
		// create database
		exec('php '.PROG_FILE.' database:create '.$db_name);
		// create username
		exec('php '.PROG_FILE.' database:create:user --password='.$db_pass.' '.$db_user.' '.$db_name);
		
		
		//$output->writeln('Config setting site...');
		//exec('cd '.$SITE_PATH.' ; php bin/magento setup:config:set --backend-frontname="admin" --db-host="'.$db_host.'" --db-name="'.$db_name.'" --db-user="'.$db_user.'" --db-password="'.$db_pass.'"', 
		//	$exec_output);
		
		$backend_name = 'admin';
		if(\Config::get('BACKEND_NAME')) $backend_name = \Config::get('BACKEND_NAME');
		
		$output->writeln('Installing site...');
		exec('cd '. $HTDOCS_PATH .' ; php bin/magento setup:install \
		--backend-frontname="'.$backend_name.'" --db-host="'.$db_host.'" --db-name="'.$db_name.'" --db-user="'.$db_user.'" --db-password="'.$db_pass.'" \
		--base-url="'.rtrim(\Config::get('BASE_URL'), '\/').'/'.$input->getArgument('Name').'/" \
		--admin-firstname=Magento --admin-lastname=User --admin-email=admin@magestore.com \
		--admin-user=admin --admin-password=admin123 --language=en_US \
		--currency=USD --timezone=America/Chicago --use-sample-data', $exec_output);
		
		$output->writeln('Upgrading site...');
		unset($exec_output);
		exec('cd '. $HTDOCS_PATH .' ; php bin/magento setup:upgrade', $exec_output); //upgrade database
		$line_out = '';
		foreach($exec_output as $l_out){
			$line_out .= $l_out;
		}
		$output->writeln($line_out);

		//exec('cd '.$_htdocs.'/'.$input->getArgument('Name').' ; php bin/magento sampledata:reset', $exec_output);
		//exec('cd '.$_htdocs.'/'.$input->getArgument('Name').' ; php bin/magento sampledata:deploy', $exec_output);
		$output->writeln('Set permission and owner...');
		exec('cd '. $HTDOCS_PATH .' ; chown -R '.\Config::get('HTDOCS_USER').':'.\Config::get('HTDOCS_GROUP').' .', $exec_output);
		exec('cd '. $HTDOCS_PATH .' ; chmod -R g+rw .', $exec_output);
		
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

