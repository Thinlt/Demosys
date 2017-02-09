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

class CreateUser extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Create user to database and grant permission')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name% [Username]</info>
  <info>php %command.full_name% [Username] [all]</info>
  <info>php %command.full_name% [Username] [Database]</info>
  <info>php %command.full_name% --with-prefix --password="123321" [-f] [Username] [Database]</info>
  Etc:
  <info>php %command.full_name% --with-prefix --password="demo123" -f demo123 ALL</info>
  
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
		
		$u_name = explode('@', $input->getArgument('Username'));
		$u_name = reset($u_name);
		if($input->getOption('with-prefix')){
			$u_name = substr(\Config::get('MYSQL_USER_PREFIX'), 0, 9).$u_name;
		}
		
		$message = 'Creating user';
		if($input->getArgument('Username'))
			$message .= ' name '.$u_name;
		else 
			$message .= '...';
		
		$output->writeln($message);
		
		$password = '';
		if($input->getOption('password')){
			$password = 'IDENTIFIED BY \''.$input->getOption('password').'\'';
		}
		
		$sql_login = 'mysql -u '.$user.' --password='.escapeshellarg($pass).' --host='.$host;
		
		exec($sql_login.' -e "SELECT User FROM mysql.user WHERE User = \''.$u_name.'\' and Host = \''.$host.'\'" 2>/dev/null', $users);
		if(!in_array($u_name, $users) || $input->getOption('force') || $input->getOption('password')){
			if($input->getOption('force')){
				$output->writeln('The user name '.$u_name.' already existed. Force to create.');
			}
			exec($sql_login.' -e "DROP USER '.$u_name.'@'.$host.'" 2>/dev/null');
			exec($sql_login.' -e "CREATE USER \''.$u_name.'\'@\''.$host.'\' '.$password.'" 2>/dev/null', $exec_output);
		}else{
			$output->writeln('The user name '.$u_name.' already existed.');
		}
		
		//grant
		if(strtolower($input->getArgument('Grant')) == 'all'){
			exec($sql_login.' -e "GRANT ALL PRIVILEGES ON *.* TO '.$u_name.'@'.$host.' WITH GRANT OPTION; FLUSH PRIVILEGES;" 2>/dev/null', $exec_output);
		}elseif($input->getArgument('Grant')){
			exec($sql_login.' -e "GRANT ALL PRIVILEGES ON '.$input->getArgument('Grant').'.* TO '.$u_name.'@'.$host.'; FLUSH PRIVILEGES;" 2>/dev/null', $exec_output);
			$output->writeln('Grant '.$input->getArgument('Grant').' TO '.$u_name.'@'.$host);
		}else{
			//no grant
		}
		
		$output->writeln('Done!');
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('Username', InputArgument::REQUIRED, 'User name to create'),
            new InputArgument('Grant', InputArgument::OPTIONAL, 'Grant user on database | all | default none', 'none'),
			new InputOption('with-prefix', null, InputOption::VALUE_NONE, 'Include prefix in config to user name'),
			new InputOption('password', 'P', InputOption::VALUE_REQUIRED, 'Password grant for user name'),
			new InputOption('admin-user', 'u', InputOption::VALUE_OPTIONAL, 'Admin user name'),
			new InputOption('admin-pass', 'p', InputOption::VALUE_OPTIONAL, 'Password of admin user name'),
			new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force to create user no ask existed user name'),
        ));
    }
}

