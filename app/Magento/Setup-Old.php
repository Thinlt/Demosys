<?php
/**
* Setup is install source to dir installed
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

  <info>php %command.full_name% <Version></info>
  <info>php %command.full_name% 2.1.1</info>
  
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
		$output->writeln('Installing... '. $input->getArgument('Version'));
		$version = $input->getArgument('Version');
		
		// check HTDOCS config value
		$_htdocs = \Config::get('HTDOCS');
		if(!\Config::get('HTDOCS')){
			$output->writeln('The value of variable HTDOCS undefined in config.ini');
			return;
		}
		$_htdocs = rtrim($_htdocs, '\/\\');
		
		// get source code dir
		$SOURCE_CODE = trim(\Config::get('SOURCE_CODE'), '.\/\\');
		// check magento source exists
		if(!file_exists(BP.'/'.$SOURCE_CODE.'/'.$version.'/')){
			$output->writeln('Magento source version '.$version.' does not exists');
			$output->writeln('Please download it first by command <info>php '.$_SERVER['PHP_SELF'].' magento:download '.$version.'</info>');
			return;
		}
		
		// get installed dir
		$INSTALLED_DIR = trim(\Config::get('INSTALLED_DIR'), '\/\\');
		if(!$INSTALLED_DIR) $INSTALLED_DIR = 'installed';
		
		// database infomations
		$db_host = \Config::get('MYSQL_HOST');
		$db_name = \Config::get('DEMOSYS_DEFAULT_DB').str_replace(array('.', '-'), array('', '_'), $version);
		$db_user = \Config::get('DEMOSYS_DB_USER');
		$db_pass = \Config::get('DEMOSYS_DB_PASS');
		
		// check database infomations
		if(!$db_host) { $db_host = 'localhost'; }
		if(!\Config::get('DEMOSYS_DEFAULT_DB')) { $db_name = 'demosys_'; }
		if(!$db_user) { $output->writeln('Config value of DEMOSYS_DB_USER is empty'); return; }
		if(!$db_pass) { $output->writeln('Config value of DEMOSYS_DB_PASS is empty'); return; }
		
		// prepare database user and password
		// check username
		if(!\Helper::get('database')->checkDbExists($db_name)){
			$output->writeln('The database '.$db_name.' doesn\'t exists. Force to create.');
		}else{
			$output->writeln('The database '.$db_name.' already existed. Force to create.');
		}
		$output->writeln('<info>php '.PROG_FILE.' database:create -f '.$db_name.'</info>');
		exec('php '.PROG_FILE.' database:create -f '.$db_name, $exec_output);
		
		// check database
		if($input->getOption('force')){
			$output->writeln('The username '.$db_user.' already existed. Force to create.');
			$output->writeln('<info>php '.PROG_FILE.' database:create:user -f --password='.$db_pass.' '.$db_user.' '.$db_name.'</info>');
			exec('php '.PROG_FILE.' database:create:user -f --password='.$db_pass.' '.$db_user.' '.$db_name, $exec_output);
		}elseif(!\Helper::get('database')->checkUserExists($db_user)){
			$output->writeln('<info>php '.PROG_FILE.' database:create:user -f --password='.$db_pass.' '.$db_user.' '.$db_name.'</info>');
			exec('php '.PROG_FILE.' database:create:user -f --password='.$db_pass.' '.$db_user.' '.$db_name, $exec_output);
		}
		
		// check installed dir
		if(!file_exists(BP.'/'.$INSTALLED_DIR.'/')){
			exec('mkdir '.BP.'/'.$INSTALLED_DIR.' >/dev/null 2>&1');
		}
		
		// check version dir
		$is_copy_require = false;
		if(file_exists(BP.'/'.$INSTALLED_DIR.'/'.$version.'/')){
			if(file_exists(BP.'/'.$INSTALLED_DIR.'/'.$version.'/app/etc/config.php')){
				exec('rm -rf '.BP.'/'.$INSTALLED_DIR.'/'.$version.' >/dev/null 2>&1'); //remove -rf
				$is_copy_require = true;
			}else{
				$output->writeln('<info>php bin/magento setup:uninstall</info>');
				$this->exec_process('cd '.BP.'/'.$INSTALLED_DIR.'/'.$version.'/ ; php bin/magento setup:uninstall', 'N');
			}
		}else{
			$is_copy_require = true;
		}
		
		if($is_copy_require){
			$output->writeln('Copying from '.BP.'/'.$SOURCE_CODE.'/'.$version.'/ to '.BP.'/'.$INSTALLED_DIR.'/'.$version.'/ ...');
			exec('cp -R '.BP.'/'.$SOURCE_CODE.'/'.$version.'/ '.BP.'/'.$INSTALLED_DIR.'/'.$version.'/', $exec_output); //copy source to disk
			$output->writeln('Copy is complete.');
		}
		
		$SITE_DIR = BP.'/'.$INSTALLED_DIR.'/'.$version.'/';
		
		// setting composer repositories
		exec('cd '.$SITE_DIR.' ; composer config repositories.magento composer https://repo.magento.com 2>/dev/null', $exec_output); //copy source to disk
		$composer_file = $SITE_DIR.'composer.json';
		if(!file_exists($composer_file)){
			$output->writeln('Composer file not found in '.$SITE_DIR);
			return;
		}
		
		// update composer back to the composer file contents
		\App\Setting::updateComposer($composer_file); //update require sampledata module of composer file
		
		// run composer update
		$output->writeln('Update composer...');
		//exec('cd '.$SITE_DIR.' ; composer update 2>/dev/null', $exec_output);
		$this->exec_process('cd '.$SITE_DIR.' ; composer update');
		
		$backend_name = 'admin';
		if(\Config::get('BACKEND_NAME')) $backend_name = \Config::get('BACKEND_NAME');
		
		$output->writeln('Installing site...');
		
		$install_command = 'cd '.$SITE_DIR.' ; php bin/magento setup:install \
		--backend-frontname="'.$backend_name.'" --db-host="'.$db_host.'" --db-name="'.$db_name.'" --db-user="'.$db_user.'" --db-password="'.$db_pass.'" \
		--base-url="'.rtrim(\Config::get('BASE_URL'), '\/').'/'.$version.'/" \
		--admin-firstname=Magento --admin-lastname=User --admin-email=admin@magestore.com \
		--admin-user=admin --admin-password=admin123 --language=en_US \
		--currency=USD --timezone=America/Chicago --use-sample-data';
		$output->writeln('<info>'.$install_command.'</info>');
		exec($install_command, $exec_output); //install exec
		
		$output->writeln('Upgrading site...');
		unset($exec_output);
		exec('cd '.$SITE_DIR.' ; php bin/magento setup:upgrade', $exec_output); //upgrade database
		$line_out = '';
		foreach($exec_output as $l_out){
			$line_out .= $l_out;
		}
		$output->writeln($line_out);
		
		$output->writeln('Set permission and owner...');
		$output->writeln('<info>cd '.$SITE_DIR.' ; chown -R '.\Config::get('HTDOCS_USER').':'.\Config::get('HTDOCS_GROUP').' .</info>');
		exec('cd '.$SITE_DIR.' ; chown -R '.\Config::get('HTDOCS_USER').':'.\Config::get('HTDOCS_GROUP').' .', $exec_output);
		$output->writeln('<info>cd '.$SITE_DIR.' ; chmod -R g+rw .</info>');
		exec('cd '.$SITE_DIR.' ; chmod -R g+rw .', $exec_output);
		
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
									 array(rtrim(\Config::get('BASE_URL'), '\/').'/'.$version.'/', 
										   rtrim(\Config::get('BASE_URL'), '\/').'/'.$version.'/admin',
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
            new InputArgument('Version', InputArgument::REQUIRED, 'The name of site you going to install as url path http://demo.example.com/<Version>/'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force to re-create database and username'),
        ));
    }
	
	protected function exec_process($cmd, $confirm = ''){
		$descriptorspec = array(
		   0 => array("pipe", "w"),   // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
		   2 => array("pipe", "w")    // stderr is a pipe that the child will write to
		);
		flush();
		$process = proc_open($cmd, $descriptorspec, $pipes);
		if (is_resource($process)) {
			if($confirm){
				fwrite($pipes[0], $confirm.PHP_EOL);
			}
			
			$err = fgets($pipes[2]);
			while (($s = fgets($pipes[1])) || ($err = fgets($pipes[2]))) {
				if($s) print $s;
				if($err) print $err;
				flush();
			}
			
			fclose($pipes[0]);
			fclose($pipes[1]);
			fclose($pipes[2]);
		}
		
		$return_value = proc_close($process);
		return $return_value;
	}
}

