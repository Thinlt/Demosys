<?php
/**
* Setup is install source to dir installed
*/

namespace App\Config;

use Symfony\Component\Console\Command\Command as Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Ssh extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Config ssh etc ssh public key.')
            ->setHelp(<<<EOF
The <info>%command.name% Options</info>

  The Options:
  <info>Key : Generate a ssh public key</info>
  <info>...</info>
  
EOF
            )
        ;
    }
	
	
	/**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$optional = $input->getArgument('Options');

		$key = 'No key';
		switch(strtolower($optional)){
			case 'key': //generate a ssh public key
			default:
				//exec('ssh-keygen -t rsa -b 4096 -C "demosys" '.PHP_EOL.' '.PHP_EOL, $ext_output);
				$descriptorspec = array(
					0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
					1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
					2 => array("file", "/dev/null", "a") // stderr is a file to write to
				);
				$process = proc_open('ssh-keygen -t rsa -b 4096 -C "demosys" ', $descriptorspec, $pipes);
				if (is_resource($process)) {
					fwrite($pipes[0], PHP_EOL);
					fwrite($pipes[0], 'n');
					fclose($pipes[0]);
					$ext_output = proc_close($process);
				}
				exec('eval "$(ssh-agent -s)"', $ext_output);
				exec('ssh-add ~/.ssh/id_rsa', $ext_output);
				$key = exec('cat ~/.ssh/id_rsa.pub', $ext_output);
				break;
		}
		
		$output_screen = <<<EOF
SSH Key:

  <info>SSH public key:
%key%</info>
  
EOF;

		$output_screen = str_replace(
			array('%key%'),
			array($key),
			$output_screen);
		
		$output->writeln($output_screen);
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('Options', InputArgument::OPTIONAL, 'Option parameter.'),
        ));
    }

}

