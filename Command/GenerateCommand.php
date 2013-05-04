<?php

namespace Ano\GeneratorBundle\Command;

use Ano\CqrsBundle\Command\Plugin\EventPlugin;
use Ano\GeneratorBundle\Command\Helper\DialogHelper;
use Ano\GeneratorBundle\Command\Plugin\GenericObjectPlugin;
use Ano\GeneratorBundle\Command\Plugin\PluginInterface;
use Ano\GeneratorBundle\Command\Plugin\ValidationConstraintsPlugin;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class GenerateCommand extends ContainerAwareCommand
{
    const OP_OBJECT_NAME = 'object_name';
    const OP_MEMBER = 'member';

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array|PluginInterface[]
     */
    protected $plugins = array();

    protected $objectType;
    protected $objectPath;
    protected $members;

    protected function configure()
    {
        $this
            ->setName('ano_generator:generate')
            ->setDescription('Generates something ;-)')
            ->setHelp('');
    }

    /**
     * @throws \InvalidArgumentException When the bundle doesn't end with Bundle (Example: "Bundle/MySampleBundle")
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();

        if ($input->isInteractive()) {
            if (!$dialog->askConfirmation($output, $dialog->getQuestion('Do you confirm generation', 'yes', '?'), true)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $dialog->writeSection($output, 'Command generation');

        $output->writeln('Generating the command code: <info>OK</info>');

        $dialog->writeGeneratorSummary($output, array());
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->loadOptions();
        $this->loadPlugins();

        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, 'Welcome to the code generator');

        // namespace
        $output->writeln(array(
            '',
            'This command helps you generate PHP objects.',
            '',
            'First, you need to give the object <comment>type</comment> you want to generate.',
//            'You must use the shortcut notation like <comment>AcmeBlogBundle:PostArticle</comment>.',
            ''
        ));

        $output->writeln('<info>Available object types:</info> ');

        $count = 20;
        $types = $this->getObjectTypes();
        foreach ($types as $i => $type) {
            if ($count > 50) {
                $count = 0;
                $output->writeln('');
            }
            $count += strlen($type);
            $output->write(sprintf('<comment>%s</comment>', $type));
            if (count($types) != $i + 1) {
                $output->write(', ');
            } else {
                $output->write('.');
            }
        }
        $output->writeln('');

        while (true) {
            $objectType = $dialog->ask($output, $dialog->getQuestion('The object type name', null), null, $types);

            if (in_array($objectType, $types)) {
                $this->objectType = $objectType;

                break;
            }

            $output->writeln(sprintf('<bg=red>Object Type "%s" does not exist.</>', $objectType));
        }

        $bundleNames = array_keys($this->getContainer()->get('kernel')->getBundles());
        $objectPath = null;
        while (true) {
            $objectName = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('The object shortcut name (ie: <info>AcmeDemoBundle:Model/User</info>)', null),
                array('Ano\GeneratorBundle\Command\Validators', 'validateObjectName'),
                false,
                null,
                $bundleNames
            );

            list($bundle, $objectName) = $this->parseShortcutNotation($objectName);

            try {
                $b = $this->getContainer()->get('kernel')->getBundle($bundle);
                foreach($this->plugins as $plugin) {
                    if ($this->objectType == $plugin->getObjectType()) {
                        $objectPath = $plugin->visitObjectPath($objectName);
                    }
                }

                $this->objectPath = $objectPath;
                if (!file_exists($b->getPath() . '/' . ltrim($this->objectPath, '/'))) {
                    break;
                }

                $output->writeln(sprintf('<bg=red>Object file "%s:%s" already exists</>.', $bundle, $this->objectPath));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }
        }

        // members
        $this->members = $this->addMembers($input, $output, $dialog);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf("You are going to generate a \"<info>%s</info>\" object", $this->objectPath),
            //sprintf('With the followings members:'),
//            var_export($this->members, true),
            '',
        ));
    }

    private function addMembers(InputInterface $input, OutputInterface $output, DialogHelper $dialog)
    {
        $members = array();
        $output->writeln(array(
            '',
            'Instead of starting with a blank command, you can add some properties now.',
        ));

        while (true) {
            $output->writeln('');
            $self = $this;

            // member name
            $name = $dialog->askAndValidate($output, $dialog->getQuestion('New member name', null), function ($name) use ($members, $self) {
                if (isset($members[$name])) {
                    throw new \InvalidArgumentException(sprintf('Member "%s" is already defined.', $name));
                }

                return $name;
            });
            if (!$name) {
                break;
            }

            // member description
            $description = $dialog->ask(
                $output,
                $dialog->getQuestion(sprintf('<comment>%s</comment> member description', $name), null)
            );

            // member access level
            $defaultAccessLevel = $this->options['member']['defaults']['access_level'];
            $accessLevel = $dialog->ask(
                $output,
                $dialog->getQuestion(sprintf('<comment>%s</comment> member access level', $name), $defaultAccessLevel),
                $defaultAccessLevel,
                array(
                    'public',
                    'private',
                    'protected',
                )
            );

            // member type
            $defaultType = $this->options['member']['defaults']['type'];
            $type = $dialog->ask(
                $output,
                $dialog->getQuestion(sprintf('<comment>%s</comment> member type', $name), $defaultType),
                $defaultType,
                array(
                    'string',
                    'array',
                    'int',
                    'boolean',
                    'float',
                )
            );

            // getters & setters
            $getter = false;
            $setter = false;
            if ('public' !== $accessLevel) {
                $defaultGetter = $this->options['member']['defaults']['getter'];
                $getter = $dialog->askConfirmation(
                    $output,
                    $dialog->getQuestion('Generate getter', $defaultGetter ? 'yes' : 'no', '?'),
                    $defaultGetter
                );

                $defaultSetter = $this->options['member']['defaults']['setter'];
                $setter = $dialog->askConfirmation(
                    $output,
                    $dialog->getQuestion('Generate setter', $defaultSetter ? 'yes' : 'no', '?'),
                    $defaultSetter
                );
            }

            $member = array(
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'accessLevel' => $accessLevel,
                'getter' => $getter,
                'setter' => $setter,
            );

            foreach($this->plugins as $plugin) {
                $member = $plugin->visitMember($member, $output, $dialog);
            }

            $members[$name] = $member;
        }

        return $members;
    }

    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get('dialog');
        if (!$dialog || get_class($dialog) !== 'Ano\GeneratorBundle\Command\Helper\DialogHelper') {
            $this->getHelperSet()->set($dialog = new DialogHelper());
        }

        return $dialog;
    }

    protected function parseShortcutNotation($shortcut)
    {
        $name = str_replace('/', '\\', $shortcut);

        if (false === $pos = strpos($name, ':')) {
            throw new \InvalidArgumentException(sprintf('The object name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Blog/Post)', $name));
        }

        return array(substr($name, 0, $pos), substr($name, $pos + 1));
    }

    protected function loadPlugins()
    {
        $this->plugins[] = new GenericObjectPlugin();
        $this->plugins[] = new ValidationConstraintsPlugin();
    }

    protected function getObjectTypes()
    {
        $types = array();
        foreach($this->plugins as $plugin) {
            $type = $plugin->getObjectType();
            if ($type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    protected function loadOptions()
    {
        //TODO: $this->options = $this->getContainer()->getParameter('ano_generator.config');
        $this->options = array(
            'member' => array(
                'defaults' => array(
                    'type' => 'string',
                    'access_level' => 'private',
                    'getter' => true,
                    'setter' => true,
                )
            )
        );
    }
}
