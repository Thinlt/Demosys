<?php
/**
* Download magento source code by version
*/

namespace App\Database;

use Symfony\Component\Console\Command\Command as Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CreateDatabase extends Command
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

  <info>php %command.full_name% <name></info>
  
EOF
            )
        ;
    }
	
	
	/**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$user = '';
		$pass = '';
		
		//get user name and password from commandline
		if($input->getOption('admin-user')){
			$user = $input->getOption('admin-user');
		}else{
			$user = \Config::get('MYSQL_ROOT_USER');
		}
		if($input->getOption('admin-pass')){
			$pass = $input->getOption('admin-pass');
		}else{
			$pass = \Config::get('MYSQL_ROOT_PASS');
		}
		
		if(\Config::get('MYSQL_HOST')){
			$host = \Config::get('MYSQL_HOST');
		}else{
			$host = 'localhost';
		}
		
		// check database username password
		$db_error = false;
		if (!$user) { $output->writeln('<error>ERROR: database user required</error>'); $db_error = true; }
		if (!$pass) { $output->writeln('<error>ERROR: database password required</error>'); $db_error = true; }
		if ($db_error) return;
		
		// processing..
		
		$db_name = $input->getArgument('name');
		if($input->getOption('with-prefix')){
			$db_name = \Config::get('MYSQL_DBNAME_PREFIX').$db_name;
		}
		
		$message = 'Creating database';
		if($input->getArgument('name'))
			$message .= ' name '.$db_name;
		else 
			$message .= '...';
		
		$output->writeln($message);
		
		if($input->getOption('force')){
			exec('mysql -u '.$user.' --password='.escapeshellarg($pass).' --host='.$host.' -e "DROP DATABASE IF EXISTS '.$db_name.'" 2>/dev/null', $databases);
		}else{
			exec('mysql -u '.$user.' --password='.escapeshellarg($pass).' --host='.$host.' -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''.$db_name.'\'" 2>/dev/null', $databases);			
			if(in_array($db_name, $databases)){
				$output->writeln('Database name '.$db_name.' already existed.');
				return;
			}
		}
		
		exec('mysql -u '.$user.' --password='.escapeshellarg($pass).' --host='.$host.' -e "CREATE DATABASE '.$db_name.'" 2>/dev/null', $exec_output);
		
		
		$output->writeln('Complete!');
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('name', InputArgument::REQUIRED, 'Database name to create'),
			new InputOption('admin-user', 'u', InputOption::VALUE_OPTIONAL, 'Database user name'),
			new InputOption('admin-pass', 'p', InputOption::VALUE_OPTIONAL, 'Password of database user name'),
			new InputOption('with-prefix', null, InputOption::VALUE_NONE, 'Include prefix in config to database name'),
			new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force to create database no ask existed database'),
        ));
    }
}

