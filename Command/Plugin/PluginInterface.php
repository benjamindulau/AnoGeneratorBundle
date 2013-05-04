<?php

namespace Ano\GeneratorBundle\Command\Plugin;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Output\OutputInterface;

interface PluginInterface
{
    /**
     * @param string $operation
     * @return boolean
     */
    public function supportOperation($operation);

    /**
     * @return string
     */
    public function getObjectType();

    /**
     * @param string $objectPath
     * @return string
     */
    public function visitObjectPath($objectPath);

    /**
     * @param array $member
     * @return array
     */
    public function visitMember(array $member, OutputInterface $output, DialogHelper $dialog);
}