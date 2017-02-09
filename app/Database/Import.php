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

class Import extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Import sql from to a database, create database if not exists.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name% <Db_Name> <File></info>
  
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
		
		$db_name = $input->getArgument('Db_Name');
		$file = $input->getArgument('File');
		
		// processing...
		$output->writeln('processing...');
		$user_pass = '-u '.$user.' --password='.escapeshellarg($pass).' --host='.$host;
		$sql_login = 'mysql '.$user_pass;
		
		exec($sql_login.' -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''.$db_name.'\'" 2>/dev/null', $databases);
		if(!in_array($db_name, $databases)){
			exec($sql_login.' -e "CREATE DATABASE IF NOT EXISTS '.$db_name.'" 2>/dev/null');
		}
		if(file_exists($file)){
			$array = explode('.', $file);
			$extension = end($array);
			if($extension == 'sql'){
				exec($sql_login.' '.$db_name.' < '.$file.' 2>/dev/null');
			}elseif($extension == 'zip'){
				exec('unzip -p '.$file.' | '.$sql_login.' '.$db_name.' 2>/dev/null');
			}elseif($extension == 'gz'){
				exec('gunzip < '.$file.' | '.$sql_login.' '.$db_name.' 2>/dev/null');
			}
			else{
			}
		}
		
		if(in_array($db_name, $databases)){
			
		}else{
			$output->writeln('No database '.$db_name);
			return;
		}
		$output->writeln('Done!'); //return value
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('Db_Name', InputArgument::REQUIRED, 'Database name to import'),
            new InputArgument('File', InputArgument::REQUIRED, 'Sql file (.sql, .zip, .gz)'),
			new InputOption('admin-user', 'u', InputOption::VALUE_OPTIONAL, 'Database user name'),
			new InputOption('admin-pass', 'p', InputOption::VALUE_OPTIONAL, 'Database password')
        ));
    }
}

