<?php

namespace Ano\GeneratorBundle\Command\Plugin;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Output\OutputInterface;

class GenericObjectPlugin implements PluginInterface
{
    public function getObjectType()
    {
        return 'generic';
    }

    public function supportOperation($operation)
    {
        return 'property' === $operation;
    }

    public function visitObjectPath($objectPath)
    {
        return str_replace('\\', '/', $objectPath).'.php';
    }

    public function visitMember(array $member, OutputInterface $output, DialogHelper $dialog)
    {
        return $member;
    }
}