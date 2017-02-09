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

class Show extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Show database or more')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name%</info>
  
  <info>php %command.full_name% [--database | -d | DATABASE] | [--table | -t | TABLE <db name>]</info>
  - default is show databases
  <info>php %command.full_name% USERS</info> - Show users in database
  
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
		$host = 'localhost';

		//get user name and password from commandline
		if($input->getOption('user')){
			$user = $input->getOption('user');
		}else{
			$user = \Config::get('MYSQL_ROOT_USER');
		}
		if($input->getOption('password')){
			$pass = $input->getOption('password');
		}else{
			$pass = \Config::get('MYSQL_ROOT_PASS');
		}
		
		if($user == '' || $pass == '' || !\Config::get('MYSQL_ROOT_USER') || !\Config::get('MYSQL_ROOT_PASS')){
			$output->writeln('<error>ERROR: The value of variables MYSQL_ROOT_USER or MYSQL_ROOT_PASS not set in config.ini</error>');
			return;
		}
		
		$case_string = strtoupper($input->getArgument('case'));
		if($input->getOption('tables')){
			$case_string = 'TABLE';
			//tranfer 1st argument to 2rd argument for database name
			$input->setArgument('db_name', $input->getArgument('case'));
		}
		
		$sql_login = 'mysql -u '.$user.' --password='.escapeshellarg($pass).' --host='.$host;
		
		switch($case_string){
			case 'USERS':
				exec($sql_login.' -e "SELECT User FROM mysql.user"', $databases);
				break;
			case 'TABLE':
				if(($db_name = $input->getArgument('db_name'))){
					exec('mysql -u '.$user.' -p'.escapeshellarg($pass).' -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = \''.$db_name.'\'"', $databases);
				}else{
					$output->writeln('No specify database name in argument');
					return;
				}
				break;
				
			case 'DATABASE':
			default:
				@exec('mysql -u '.$user.' -p'.escapeshellarg($pass).' -s -e "SHOW DATABASES"', $databases);
		}
		
		//showing
		$temp = '';
		array_shift($databases);
		foreach($databases as $line){
			if(substr($line, 0, 8) == 'Warning:') continue;
			if($input->getOption('line')){
				$temp .= $line. ' ';
			}else{
				$temp .= $line.PHP_EOL;
			}
		}
		
		$output->writeln(rtrim($temp));
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('case', InputArgument::OPTIONAL, 'Show case default [database] or [table]'),
            new InputArgument('db_name', InputArgument::OPTIONAL, 'Database name to show tables in'),
			new InputOption('databases', 'd', InputOption::VALUE_NONE, 'Show case database -d'),
			new InputOption('tables', 't', InputOption::VALUE_NONE, 'Show case table -t'),
			new InputOption('line', 'l', InputOption::VALUE_NONE, 'Show in one line'),
			new InputOption('user', 'u', InputOption::VALUE_OPTIONAL, 'Database user name'),
			new InputOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password of database user name'),
        ));
    }
}

