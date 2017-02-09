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

class Backup extends Command
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
		
		$db_name = $input->getArgument('DB-Name');
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
				if(\Config::get('BACKUP_DIR')){
					$dump_path = BP.'/'.\Config::get('BACKUP_DIR').'/';
				}else{
					$dump_path = BP.'/databases/';
				}
				if(!file_exists($dump_path)) exec('mkdir -p '.$dump_path); //mkdir path
				date_default_timezone_set('UTC');
				$dump_file = $dump_path.$db_name.'_'.date('Ymd_His').'.sql';
				// export schema sql
				exec('mysqldump '.$user_pass.' --opt --single-transaction --no-data '.$db_name.' > '.$dump_file .' 2>/dev/null');
				// export data tables
				exec('echo "'.$before_sql.'" >> '.$dump_file);
				foreach($tables as $tb_name){
					if(in_array($tb_name, $this->_ignore_tables)){
						continue;
					}
					exec('mysqldump '.$user_pass.' --opt --single-transaction --no-create-db --no-create-info '.$db_name.' '.$tb_name.' >> '.$dump_file .' 2>/dev/null');
				}
				exec('echo "'.$after_sql.'" >> '.$dump_file);
				// compress
				exec('zip -9j '.$dump_file.'.zip '.$dump_file);
				exec('rm '.$dump_file);
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
            new InputArgument('DB-Name', InputArgument::REQUIRED, 'Database name to backup'),
			new InputOption('admin-user', 'u', InputOption::VALUE_OPTIONAL, 'Database username'),
			new InputOption('admin-pass', 'p', InputOption::VALUE_OPTIONAL, 'Database password')
        ));
    }
}

