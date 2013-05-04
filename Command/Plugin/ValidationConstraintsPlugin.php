<?php

namespace Ano\GeneratorBundle\Command\Plugin;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Output\OutputInterface;

class ValidationConstraintsPlugin implements PluginInterface
{
    protected $constraints = array(
        'Blank' => array(),
        'Date' => array(),
        'DateTime' => array(),
        'Email' => array('checkMX', 'checkHost'),
        'Length' => array('max', 'min'),
        'Range' => array('max', 'min'),
        'Url' => array('protocols'),
        'NotNull' => array(),
        'NotBlank' => array(),
    );

    public function getObjectType()
    {
        return null;
    }

    public function supportOperation($operation)
    {
        return 'property' === $operation;
    }

    public function visitObjectPath($objectPath)
    {
        return $objectPath;
    }

    public function visitMember(array $member, OutputInterface $output, DialogHelper $dialog)
    {
        $availableConstraints = array_keys($this->constraints);
        $constraints = array();
        while (true) {
            $constraintName = $dialog->askAndValidate($output, $dialog->getQuestion('New member validation constraints (press <return> to stop adding constraints)', null), function ($constraintName) use ($availableConstraints) {
                if (!empty($constraintName) && !in_array($constraintName, $availableConstraints)) {
                    throw new \InvalidArgumentException(sprintf('Constraint "%s" is not available.', $constraintName));
                }

                return $constraintName;
            }, false, null, $availableConstraints);

            if (!$constraintName) {
                break;
            }

            $options = array();
            foreach($this->constraints[$constraintName] as $optionName) {
                $options[$optionName] = $dialog->ask(
                    $output,
                    $dialog->getQuestion(sprintf(
                        '<comment>%s</comment> value for <comment>%s</comment> constraint',
                        $optionName,
                        $constraintName
                    ), null)
                );
            }

            $constraints[$constraintName] = $options;
        }

        if (!empty($constraints)) {
            $member['constraints'] = $constraints;
        }

        return $member;
    }
}