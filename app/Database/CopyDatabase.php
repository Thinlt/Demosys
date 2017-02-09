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

class CopyDatabase extends Command
{
	protected $_ignore_tables = array('cache', 'search_query', 'search_synonyms', 'session', 'catalogsearch_fulltext_scope1');
	
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

  <info>php %command.full_name% <From Db> <To Db></info>
  
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
		
		$db_name = $input->getArgument('From_Db');
		$to_db_name = $input->getArgument('To_Db');
		
		$before_sql = 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS;SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION;SET NAMES utf8;SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\';SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0;';
		$after_sql = 'SET SQL_MODE=@OLD_SQL_MODE;SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;SET SQL_NOTES=@OLD_SQL_NOTES;';
		
		// processing...
		$output->writeln('processing...');
		$user_pass = '-u '.$user.' --password='.escapeshellarg($pass).' --host='.$host;
		$sql_login = 'mysql '.$user_pass;
		
		exec($sql_login.' -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''.$db_name.'\'" 2>/dev/null', $databases);			
		if(in_array($db_name, $databases)){
			// get all table name
			exec($sql_login.' -e "SHOW TABLES IN '.$db_name.'" 2>/dev/null', $tables);
			if(count($tables) > 0){
				$dump_path = '/tmp/'.PROG_NAME.'/mysql_dump/';
				exec('mkdir -p '.$dump_path); //mkdir path
				$schema_file = $dump_path.$db_name.'.schema.sql';
				// export schema sql
				exec('mysqldump '.$user_pass.' --opt --single-transaction --no-data '.$db_name.' > '.$schema_file .' 2>/dev/null');
				// export data tables
				foreach($tables as $tb_name){
					if(in_array($tb_name, $this->_ignore_tables)){
						continue;
					}
					$dump_file = $dump_path.$db_name.'.data.'.$tb_name.'.sql';
					exec('echo "'.$before_sql.'" > '.$dump_file);
					exec('mysqldump '.$user_pass.' --opt --single-transaction --no-create-db --no-create-info '.$db_name.' '.$tb_name.' >> '.$dump_file .' 2>/dev/null');
					exec('echo "'.$after_sql.'" >> '.$dump_file);
				}
				
				// create to name db
				exec($sql_login.' -e "DROP DATABASE IF EXISTS '.$to_db_name.'; CREATE DATABASE '.$to_db_name.'" 2>/dev/null'); //drop table and create
				
				// import schema sql
				exec('mysql '.$user_pass.' '.$to_db_name.' < '.$schema_file .' 2>/dev/null');
				//exec('rm '.$schema_file); //delete after import
				
				// import data tables
				foreach($tables as $tb_name){
					if(in_array($tb_name, $this->_ignore_tables)){
						continue;
					}
					$dump_file = $dump_path.$db_name.'.data.'.$tb_name.'.sql';
					if(!file_exists($dump_file)){
						continue;
					}
					exec('mysql '.$user_pass.' '.$to_db_name.' < '.$dump_file .' 2>/dev/null');
					//exec('rm '.$dump_file); //delete after import
				}
				
				exec('rm -rf '.$dump_path); //delete after import
			}else{
				$output->writeln('No table in '.$db_name);
				return;
			}
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
            new InputArgument('From_Db', InputArgument::REQUIRED, 'Database name to copy'),
            new InputArgument('To_Db', InputArgument::REQUIRED, 'Database name copy to'),
			new InputOption('admin-user', 'u', InputOption::VALUE_OPTIONAL, 'Database user name'),
			new InputOption('admin-pass', 'p', InputOption::VALUE_OPTIONAL, 'Database password')
        ));
    }
}

