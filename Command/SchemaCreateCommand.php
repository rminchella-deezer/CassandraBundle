<?php

namespace CassandraBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SchemaCreateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('cassandra:schema:create')
            ->setDescription('Drop and create cassandra table')
            ->addArgument(
                'connection',
                InputArgument::OPTIONAL,
                'Connection of cassandra'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $container = $this->getContainer();
        $connection = $input->getArgument('connection') ?: 'default';
        $em = $container->get(sprintf('cassandra.%s_entity_manager', $connection));
        $schemaManager = $em->getSchemaManager();

        // Get all files in src/*/Entity directories
        $path = $container->getParameter('kernel.root_dir').'/../src';
        $iterator = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            ),
            '/^.+'.preg_quote('.php').'$/i',
            \RecursiveRegexIterator::GET_MATCH
        );
        foreach ($iterator as $file) {
            $sourceFile = $file[0];
            if (!preg_match('(^phar:)i', $sourceFile)) {
                $sourceFile = realpath($sourceFile);
            }
            if (preg_match('/src\/.*Entity\//', $sourceFile)) {
                $className = str_replace('/', '\\', preg_replace('/(.*src\/)(.*).php/', '$2', $sourceFile));
                $metadata = $em->getClassMetadata($className);
                $tableName = $metadata->table['name'];
                $primaryKeys = isset($metadata->table['primaryKeys']) ? $metadata->table['primaryKeys'] : ['id'];

                if ($tableName) {
                    $schemaManager->dropTable($tableName);
                    $schemaManager->createTable($tableName, $metadata->fieldMappings, $primaryKeys);
                }
            }
        }

        $output->writeln('Cassandra schema updated successfully!');
        $em->closeAsync();
    }
}
