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

class Drop extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Drop database')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name% [Database name]</info>
  <info>php %command.full_name% demosys_210</info>
  
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
		
		
		// processing...
		
		$db_name = $input->getArgument('Name');
		
		$sql_login = 'mysql -u '.$user.' --password='.escapeshellarg($pass).' --host='.$host;
		
		exec($sql_login.' -e "DROP DATABASE IF EXISTS '.$db_name.'"');		
		
		$output->writeln('Complete!');
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('Name', InputArgument::REQUIRED, 'Drop database Name'),
			new InputOption('admin-user', 'u', InputOption::VALUE_OPTIONAL, 'Admin user name'),
			new InputOption('admin-pass', 'p', InputOption::VALUE_OPTIONAL, 'Admin user password'),
        ));
    }
}

