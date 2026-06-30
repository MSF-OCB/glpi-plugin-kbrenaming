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
use Item_SoftwareVersion;
use Manufacturer;
use OperatingSystem;
use Plugin;
use PluginKbrenamingKb;
use PluginKbrenamingToolbox;
use Software;
use SoftwareVersion;
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

class RenamesoftwarekbCommand extends AbstractCommand{

    protected function configure(): void
    {
        parent::configure();

        $this->setName('Kbrenaming:kb:rename_software');
        $this->setDescription(__('renaming software Kb Microsoft'));

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $software_db = new Software();
        $softwareversion_db = new SoftwareVersion();
        $kb = new PluginKbrenamingKb();
        $manufacturer_db = new Manufacturer();
        $input_manufacturer = ['name' => 'Microsoft'];
        $manufacturers_id = $manufacturer_db->findID($input_manufacturer);
        if ($manufacturers_id < 0) {
            $manufacturers_id = $manufacturer_db->import($input_manufacturer);
        }
        $operatingsystem_db = new OperatingSystem();
        $input_operatingsystem = ['name' => 'Windows'];
        $operatingsystems_id = $operatingsystem_db->findID($input_operatingsystem);
        if ($operatingsystems_id < 0) {
            $operatingsystems_id = $operatingsystem_db->import($input_operatingsystem);
        }
//        $condition = ['name' => ['REGEXP', '^kb[0-9]{6,}$']];
//        $softwares = $software_db->find($condition);

        $query = [
            'SELECT' => [
                'id',
                'name',
                'entities_id'
            ],
            'FROM'   => \Software::getTable(),
            'WHERE'  => [
                'name' => ['REGEXP', '^kb[0-9]{6,}$'],
            ],
        ];



        $software_iterator = $this->db->request($query);
        $sofware_count = $software_iterator->count();
        if ($sofware_count === 0) {
            $output->writeln('<info>' . __('No software to process.') . '</info>');
            return 0; // Success
        }

        $progress_bar = new ProgressBar($output, $sofware_count);
        $progress_bar->start();

        $processed_count = 0;
        foreach ($software_iterator as $id=> $software){
            $progress_bar->advance(1);

            $this->writelnOutputWithProgressBar(
                sprintf(__('Processing software having id "%s".'), $software['id']),
                $progress_bar,
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $condition = ['name' => $software['name']];
            $softwareversions = $softwareversion_db->find($condition);
            if ( !empty($softwareversions)){
                if (count($softwareversions)>1){
                    $output->writeln('<error>Error : multi software versions "' . $software['name'] . '" exist in db</error>');
                    continue;
                }else{
                    $output->writeln(
                        'the software version"' . $software['name'] . '" already exist' ,
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                    $softwareversion = array_shift($softwareversions);
                }
                $output->writeln(print_r($software,1), OutputInterface::VERBOSITY_DEBUG);
                $condition = ['softwares_id' => (int) $software['id']];
                $soft_versions =  $softwareversion_db->find($condition);
                foreach ($soft_versions as $old_id => $soft_version){
                    PluginKbrenamingToolbox::change_softwareversion((int) $old_id, (int) $softwareversion['id']);
                }
            }else{
                $kbData = $kb->getByName($software['name']);
                if ($kbData === false ){
                    continue;
                }
                $kb_group = $kbData->getField("plugin_kbrenaming_kbgroup");
                if (!is_array($kb_group) || empty($kb_group['name'])) {
                    continue;
                }
                $condition = ['name' => $kb_group['name']];
                $softs = $software_db->find($condition,[],1);
                if (empty($softs)){
                    $input = $kb_group;
                    unset($input['id']);
                    unset($input['softwarecategory']);
                    $input['entities_id'] = (int) $software['entities_id'];
                    $input['is_recursive'] = 1;
                    $input['manufacturers_id'] = $manufacturers_id;
                    $soft_id = $software_db->add($input);
                    if (!$soft_id) {
                        continue;
                    }
                }else{
                    $soft = array_shift($softs);
                    $soft_id = (int) $soft['id'];
                }
                $condition = ['name' => $kbData->getField("name")];
                $soft_versions = $softwareversion_db->find($condition,[],1);
                if (empty($soft_versions)) {
                    $input = [
                        'name' => $kbData->getfield('name'),
                        'comment' => $kbData->getfield('comment'),
                        'entities_id' => (int) $software['entities_id'],
                        'is_recursive' => 1,
                        'softwares_id' => $soft_id,
                        'operatingsystems_id' => $operatingsystems_id
                    ];
                    $soft_version_id = $softwareversion_db->add($input);
                }else{
                    $soft_version = array_shift($soft_versions);
                    $soft_version_id = $soft_version['id'];

                }
                if ($soft_version_id>0){
                    $condition = ['softwares_id' => (int) $software['id']];
                    $softwareversions =  $softwareversion_db->find($condition);
                    foreach ($softwareversions as $id => $softwareversion){
//                        $this->getFromDB($this->fields['id']);
                        PluginKbrenamingToolbox::change_softwareversion((int) $id, (int) $soft_version_id);
                    }
                }
            }

            $condition = ['softwares_id' => (int) $software['id']];
            $this->output->writeln('<comment>delete "' . print_r($condition,true) . '" </comment>', OutputInterface::VERBOSITY_VERY_VERBOSE);
//            $softwareversion_db->delete($condition,true);

            $result = PluginKbrenamingToolbox::deleteSoftwareVersionsBySoftwareId((int) $software['id']);
            if($result!== false){
                $condition = ['id' => (int) $software['id']];
                $this->output->writeln('<comment>delete "' . print_r($condition,true) . '" </comment>', OutputInterface::VERBOSITY_VERY_VERBOSE);
                $software_db->delete($condition,true);

            }


            $processed_count++;

//            return 0;
        }
        $progress_bar->finish();
        $this->output->write(PHP_EOL);

        $output->writeln(
            '<info>' .sprintf(__('Number of softwares processed: %d.'), $processed_count) . '</info>'
        );

        return 0; // Success
    }
//
//    public function change_softwareversion_id(int $old_id, int $new_id): bool{
//        $this->output->writeln('<comment>softwareversion old_id : "' . $old_id . '" - new_id : "' . $new_id . '" </comment>',OutputInterface::VERBOSITY_VERBOSE);
//        $return = !class_exists('Computer_SoftwareVersion') || $this->change_softwareversion($old_id, $new_id, '\Computer_SoftwareVersion');
//        return $return && $this->change_softwareversion( $old_id,  $new_id, '\Item_SoftwareVersion');
//    }
/*    public function change_softwareversion(int $old_id, int $new_id): bool{
        $this->output->writeln('<comment>move softwareversion of "Item_SoftwareVersion" </comment>',OutputInterface::VERBOSITY_VERBOSE);
        $this->output->writeln('<comment>Item_SoftwareVersion::getIndexName()" : "' .Item_SoftwareVersion::getIndexName() . '"</comment>',OutputInterface::VERBOSITY_VERBOSE);

        $result = $this->db->query(
            "
            UPDATE `" . Item_SoftwareVersion::getTable() . "`
            SET  `" . Item_SoftwareVersion::getTable() . "`.`softwareversions_id` = '" . $new_id . "'
            WHERE `" . Item_SoftwareVersion::getTable() . "`.`softwareversions_id` = '" . $old_id . "' ;
            "
        );*/


//        $item_db = new Item_SoftwareVersion();
//        $condition = [
//            'softwareversions_id'=> $old_id
//        ];
//        $items = $item_db->find($condition);
//        $return = true;
//        foreach ($items as $id => $item){
//
//            $this->output->writeln('<comment>Item_SoftwareVersion::getIndexName()" : </comment>',OutputInterface::VERBOSITY_VERBOSE);
//            var_dump($item_db->getFromDB($item[Item_SoftwareVersion::getIndexName()])) ;
//            $this->output->writeln('<comment>"Item_SoftwareVersion" : "' . print_r($item, true) . '" </comment>',OutputInterface::VERBOSITY_VERY_VERBOSE);
//            $input = [
//                'softwareversions_id' => $new_id,
//                Item_SoftwareVersion::getIndexName() => $item[Item_SoftwareVersion::getIndexName()]
//            ];
//            $result = $item_db->update($input);
//           var_dump($result);
//            $return = $return && $result;
//            if ($result ===false){
//                $this->output->writeln('<error>Error for update : "' . print_r($input, true) . '" </error>',OutputInterface::VERBOSITY_VERY_VERBOSE);
//            }
//        }
/*        return $result;
    }*/


//    public function change_softwareversion_items(int $old_id, int $new_id): bool{
//        new \Item_SoftwareVersion();
//        return true;
//    }
}
