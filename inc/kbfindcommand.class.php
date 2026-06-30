<?php

/**
 *
 * updatekb.class.php
 *
 *
 *
 * @version GIT: $
 * @author  Sébastien Batteur <sebastien.batteur@brussels.msf.org>
 */
//namespace GlpiPlugin\Console\Sotware;
//namespace GlpiPlugin\Msf;
namespace GlpiPlugin\Kbrenaming;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

use CommonDBTM;
use DB;
use DomainType;
use Domain;
use Domain_Item;
use Plugin;
use PluginKbrenamingKb;
use Symfony\Component\Console\Input\InputArgument;
use Toolbox;
use Glpi\Console\AbstractCommand;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Question\ChoiceQuestion;

class KbfindCommand extends AbstractCommand{

    protected function configure(): void
    {
        parent::configure();

        $this->setName('Kbrenaming:kb:finder');
        $this->setDescription(__('find KB'));

        $this->addArgument(
            'kb',
            InputArgument::REQUIRED,
            'name of kb Microsoft'
        );

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbName = (string) $input->getArgument('kb');
        $kb = new PluginKbrenamingKb();
        $kbData = $kb->getByName($kbName);
        $output->writeln(print_r($kbData,1));
        $output->writeln('<info>' . __('Migration done.') . '</info>');

        return 0; // Success
    }
}
