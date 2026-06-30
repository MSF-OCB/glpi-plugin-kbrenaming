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

class PluginKbrenamingKb extends CommonDropdown {
    const MICROSOFT_CATALOG_URL = "https://www.catalog.update.microsoft.com";
    const MICROSOFT_CATALOG_URL_SEARCH = self::MICROSOFT_CATALOG_URL . "/Search.aspx?q=";
    const CATEGORIES = [
        'Security Updates'  => 'security_update',
        'Critical Updates'  => 'critical_update',
        'Feature Packs'     => 'feature_pack',
        'Updates'           => 'updates'
    ];
    const WAIT_TIME = 0.1;
    const SLEEP_TIME = 100000;
    const CATALOG_TIMEOUT = 10;
    const CATALOG_MAX_ATTEMPTS = 3;
    // From CommonDBTM
    public $dohistory          = true;
    public $can_be_translated  = true;
    /**
     * The right name for this class
     *
     * @var string
     */
    static $rightname                   = 'software';
    private string $kb;
    private string $arch = '';

    static function getTypeName($nb = 0) {
        return _n('KB', 'KB\'s', $nb);
    }


    function getAdditionalFields() {

        return [
            [
                'name'   => 'plugin_kbrenaming_kbgroups_id',
                'label'  => __('Software name'),
                'type'  => 'dropdownValue',
                'list'  => true
            ], [
                'name'  => 'disabled_update',
                'label' => __('Disable update'),
                'type'  => 'bool',
                'list'  => true
            ]
        ];
    }

//    static public function rawSearchOptionsToAdd() {
//        $tab = [];
    function rawSearchOptions() {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '3002',
            'table'              => PluginKbrenamingKbGroup::getTable(),
            'field'              => 'name',
            'name'               => __('Software name', 'kbrenaming'),
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'                 => '3003',
            'table'              => $this->getTable(),
            'field'              => 'disabled_update',
            'name'               => __('Disabled update', 'kbrenaming'),
            'datatype'           => 'bool'
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
        if (!empty($input['plugin_kbrenaming_kbgroup']['name'])){
            $kbgroups = new PluginKbrenamingKbGroup();
            $condition = ['name' => $input['plugin_kbrenaming_kbgroup']['name']];
            $input['plugin_kbrenaming_kbgroups_id'] = $kbgroups->findID($condition);
            if($input['plugin_kbrenaming_kbgroups_id'] === -1){
                unset($input['plugin_kbrenaming_kbgroup']['id']);
                $input['plugin_kbrenaming_kbgroups_id'] = $kbgroups->add($input['plugin_kbrenaming_kbgroup']);
            }
        }
        return $input;
    }

    function post_getFromDB() {
        // PluginKbrenamingKbGroup
        $kbgroups_id = (int) $this->getField('plugin_kbrenaming_kbgroups_id');
        if ($kbgroups_id > 0 && $kbgroups = PluginKbrenamingKbGroup::getById($kbgroups_id)){
            $this->fields['plugin_kbrenaming_kbgroup'] = $kbgroups->fields;
        }
    }

    public function getByName(string $name){

        $condition['name'] = $name;
        $id = $this->findID($condition);
        if ($id !== -1){
            return  $this->getById($id);
        }
        if (preg_match('/^kb[0-9]{6,}$/i', $name, $matches) !== 1){
            return false;
        }
        $id = $this->findInMsCatalog($name);

        if ($id !== -1){
            return  $this->getById($id);
        }else{
            return false;
        }
    }

    public function findInMsCatalog(string $kb_name): int{

        if (!empty($this->arch)){
            $searchs[] = ['q'=> '(' . $kb_name . ') ' . $this->arch, 'sub' => '(' . $kb_name . ') '];
        }
        $searchs[] = ['q'=> '(' . $kb_name . ')', 'sub' => '(' . $kb_name . ') '];
//        $searchs[] = ['q'=> $kb_name, 'sub' =>  $kb_name];
        $kb_data['name'] = $kb_name;
        $previous_libxml_state = libxml_use_internal_errors(true);
        try {
            foreach ($searchs as $search){
                $file = $this->fetchCatalogSearchPage($search['q']);
                if ($file === null) {
                    continue;
                }

                $dom = new DOMDocument();
                if (!$dom->loadHTML($file, LIBXML_NOWARNING)) {
                    unset($dom);
                    continue;
                }
                unset($file);

                $table_container = $dom->getElementById('tableContainer');
                if ($table_container === null) {
                    unset($dom);
                    continue;
                }

                $table = $table_container->getElementsByTagName('tr');
                if ($table->length <= 1){
                    unset($table, $dom);
                    continue;
                }
                $title_diff = '';
                $selected_row = null;
                for($i=1; $i < $table->length; $i++){
                    $row = $table[$i];
                    $product = self::getTableCellText($row, 2);
                    if (stripos($product, 'GDR-DU')!==false){
                        continue;
                    }

                    $title = self::getTableCellText($row, 1);
                    if (stripos($title, '(' . $kb_name . ')')===false){
                        continue;
                    }

                    $selected_row = $row;
                    $title_diff = trim(
                        PluginKbrenamingToolbox::str_union($title_diff, $title, 0, 16),
                        " \t\n\r\0\x0B-_"
                    );
                }

                if ($selected_row === null) {
                    unset($table, $dom);
                    continue;
                }

                $kb_data=array_merge_recursive($kb_data, $this->__find_name_and_comments( $title_diff, $kb_data['name'] ));
                if (empty($kb_data['comment'])){
                    $kb_data['comment'] = self::getTableCellText($selected_row, 1);
                }
                $category = self::getTableCellText($selected_row, 3);
                $kb_data['plugin_kbrenaming_kbgroup']['softwarecategory']['name'] = self::CATEGORIES[$category] ?? $category;
//            var_dump($kb_data);
                $id = $this->add($kb_data);
//            $id = false;
                return ($id !== false)?(int) $id:-1;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_libxml_state);
        }
        return -1;
    }

    private function fetchCatalogSearchPage(string $query): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout'       => self::CATALOG_TIMEOUT,
                'ignore_errors' => true,
                'header'        => "User-Agent: GLPI kbrenaming plugin\r\n"
            ]
        ]);

        for ($attempt = 1; $attempt <= self::CATALOG_MAX_ATTEMPTS; $attempt++) {
            while (PluginKbrenamingToolbox::getLastRequest() + self::WAIT_TIME >= microtime(true)) {
                usleep(self::SLEEP_TIME);
            }
            PluginKbrenamingToolbox::setLastRequest();

            $file = @file_get_contents(
                self::MICROSOFT_CATALOG_URL_SEARCH . rawurlencode($query),
                false,
                $context
            );

            if (is_string($file) && $file !== '') {
                return $file;
            }

            usleep(self::SLEEP_TIME);
        }

        return null;
    }

    private static function getTableCellText(DOMElement $row, int $index): string
    {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length <= $index) {
            return '';
        }

        return trim($cells[$index]->textContent);
    }

/*    function str_union(string $string1, string $string2, int $num = 0): string{
        if (empty($string1)) {
            return $string2;
        }
        if (empty($string2)) {
            return $string1;
        }
        $return = '';
        for($i=0; $i<min(strlen($string1), strlen($string2)); $i++){
            if (strcasecmp($string1[$i], $string2[$i]) == 0){
                $return .= $string1[$i];
            }else{
                break;
            }
        }
        $string = [$string1,$string2];
        return $return?:$string[$num];
    }*/

/*    private function __getKb($row,array &$kb_data = []):array{

        if (empty($kb_data['name'])){
            return [];
        }

        $name = trim($row->getElementsByTagName('td')[1]->textContent);
        $kb_data=array_merge_recursive($kb_data, $this->__find_name_and_comments( $name, $kb_data['name'] ));

        $category = trim($row->getElementsByTagName('td')[3]->textContent);
        $kb_data['plugin_kbrenaming_kbgroup']['softwarecategory']['name'] = self::CATEGORIES[$category] ?? $category;

        return $kb_data;

    }*/

    private function __find_name_and_comments( $name, $kb_name ): array {
        $title = $name;
        $date = '';
        if (preg_match('/^(\d{4}-\d{2}) (.*)$/i',$name, $matches) === 1){
            $date = $matches[1] . ' ';
            $title = $matches[2];
            $end_title = ' for Windows ';
            if (($nb_char = stripos($title, $end_title))!==false){
                $title = substr( $title, 0, $nb_char + strlen($end_title) - 1);
            }else{
                if (preg_match('/^(.*) \(kb\d{6,}\)$/i',$name, $matches) === 1){
                    $title = $matches[1];
                }
            }
        }else{
            if (preg_match('/^(.*) \(kb\d{6,}\)$/i',$name, $matches) === 1){
                $title = $matches[1];
            }
        }
        $return['plugin_kbrenaming_kbgroup']['name'] = $title;
        if (!empty($title)){
            $return['comment'] = $date . $return['plugin_kbrenaming_kbgroup']['name'] . ' (' . $kb_name . ')';
        }
        return $return;
    }

    public function set_arch($software_arch, $os_arch): string{
        switch ($software_arch){
            case 'x86_64':
                $this->arch = 'x64';
                break;
            case 'i586':
                $this->arch = 'x86';
                break;
            case 'arm64':
                $this->arch = 'ARM64';
                break;
            case 'neutral':
            default:
                switch ($os_arch){
                    case '64-bit':
                        $this->arch = 'x64';
                        break;
                    case '32-bit':
                        $this->arch = 'x86';
                        break;
                    default:
                        $this->arch = '';
                        break;
                }
                break;
        }
        return $this->arch;
    }

}
