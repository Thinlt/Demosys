<?php

namespace App;

use Symfony\Component\Console\Command\Command as Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command
{
	/**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setDescription('Test commands')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name%</info>

You can also display the commands for a specific namespace:

  <info>php %command.full_name% test</info>

You can also output the information in other formats by using the <comment>--format</comment> option:

  <info>php %command.full_name% --format=xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

  <info>php %command.full_name% --raw</info>
EOF
            )
        ;
    }
	
	
	/**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        /* $helper = new DescriptorHelper();
        $helper->describe($output, $this->getApplication(), array(
            'format' => $input->getOption('format'),
            'raw_text' => $input->getOption('raw'),
            'namespace' => $input->getArgument('namespace'),
        )); */
		$output->writeln('Hello test!');
		
		$name = $input->getFirstArgument();
		$output->writeln('The arg1 value is '.$name);
		
		$arg2 = $input->getArgument('arg2');
		$output->writeln('The arg2 value is '.$arg2);
		
		$argvs = $input->getArguments();
		$output->writeln('All argument values: '.implode(', ', $argvs));
		
		$opts = $input->getOptions();
		$output->writeln('Options: '. implode(', ', $opts));
		
		
		
		$output->writeln('..End');
		
    }
	
	
	/**
     * {@inheritdoc}
     */
    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('arg2', InputArgument::OPTIONAL, 'The argument name'),
            new InputOption('opt1', null, InputOption::VALUE_NONE, 'Option 1'),
            new InputOption('opt2', null, InputOption::VALUE_OPTIONAL, 'Option 2'),
            new InputOption('opt3', null, InputOption::VALUE_REQUIRED, 'Option 3', '3'),
        ));
    }
}

