<?php
/*
* all commands list define put to here
*/
return [
	'App\Magento\Download'			=> 'magento:download',
	'App\Magento\Versions'			=> 'magento:versions',
	'App\Magento\Setup'				=> 'magento:setup',
	'App\Database\CreateDatabase'	=> 'database:create',
	'App\Database\CreateUser'		=> 'database:create:user',
	'App\Database\Drop'				=> 'database:drop',
	'App\Database\DropUser'			=> 'database:drop:user',
	'App\Database\CopyDatabase'		=> 'database:copy',
	'App\Database\Show'				=> 'database:show',
	'App\Database\Import'			=> 'database:import',
	'App\Database\Backup'			=> 'database:backup',
	'App\Site\NewBlank'					=> 'site:new',
	'App\Site\CloneSite'				=> 'site:clone',
	'App\Site\Extension\Import'			=> 'site:extension:import',
	'App\Site\Extension\Install'		=> 'site:extension:install',
	'App\Config\Ssh'					=> 'config:ssh',
];
