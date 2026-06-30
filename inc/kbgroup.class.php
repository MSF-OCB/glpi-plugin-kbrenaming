<?php
/**
 *
 * kb.class.php
 *
 *
 *
 * @version GIT: $
 * @author  Sébastien Batteur <sebastien.batteur@brussels.msf.org>
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginKbrenamingKbGroup extends CommonDropdown {

    static $rightname                   = 'software';

    static function getTypeName($nb = 0) {
        return _n('Group of KB', 'Groups of KB', $nb);
    }

    function getAdditionalFields() {

        return [
            [
                'name'   => 'softwarecategories_id',
                'label'  => SoftwareCategory::getTypeName(1),
                'type'   => 'dropdownValue',
                'list'  => true
            ]
        ];
    }

    function rawSearchOptions() {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '3001',
            'table'              => SoftwareCategory::getTable(),
            'field'              => 'completename',
            'name'               => SoftwareCategory::getTypeName(1),
            'datatype'           => 'dropdown'
        ];

        return $tab;
    }

    function prepareInputForAdd($input): array
    {
        return $this->__prepareInput($input);
    }

    function prepareInputForUpdate($input): array
    {
        return $this->__prepareInput($input);
    }

    private function __prepareInput($input): array
    {
        if (!empty($input['softwarecategory']['name'])){
            $softwarecategory = new SoftwareCategory();
            $input['softwarecategories_id'] = $softwarecategory->import(['name' => $input['softwarecategory']['name']]);
        }
        return $input;
    }

    function post_getFromDB() {
        // softwarecategory
        $softwarecategories_id = (int) $this->getField('softwarecategories_id');
        if ($softwarecategories_id > 0 && $software_category = SoftwareCategory::getById($softwarecategories_id)){
            $this->fields['softwarecategory'] = $software_category->fields;
        }
    }

}
