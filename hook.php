<?php
/**
* Public IP
*
* Copyright (C) 2020-2020 by the MSF OCB.
*
* https://www.msf-azg.be
* https://github.com/msf/glpi-pliguin-msf
*
* ------------------------------------------------------------------------
*
* LICENSE
*
* This file is part of MSF project.
*
* FusionInventory is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with FusionInventory. If not, see <http://www.gnu.org/licenses/>.
*
* ------------------------------------------------------------------------
*
* This file is used to manage the setup / initialize plugin
* FusionInventory.
*
* ------------------------------------------------------------------------
*
* @package   Public IP
* @copyright Copyright (c) 2020-2020 MSF OCB
* @license   AGPL License 3.0 or (at your option) any later version
*            http://www.gnu.org/licenses/agpl-3.0-standalone.html
* @link      https://www.msf-azg.be
* @link      https://github.com/msf/glpi-plugin-msf
*
*/

/**
* Manage the installation process
*
* @return boolean
*/
function plugin_kbrenaming_is_kb_name(string $name): bool
{
    return preg_match('/^kb[0-9]{6,}$/i', trim($name)) === 1;
}

function plugin_kbrenaming_is_command_line(): bool
{
    if (function_exists('isCommandLine')) {
        return isCommandLine();
    }

    return PHP_SAPI === 'cli'
        || basename((string) filter_input(INPUT_SERVER, "SCRIPT_NAME")) === "cli_install.php";
}

function plugin_kbrenaming_add_display_preferences(string $itemtype, array $preferences): void
{
    global $DB;

    $existing = $DB->request([
        'FROM'  => DisplayPreference::getTable(),
        'WHERE' => ['itemtype' => $itemtype],
        'LIMIT' => 1
    ]);
    if ($existing->count() > 0) {
        return;
    }

    $display_preference = new DisplayPreference();
    foreach ($preferences as $preference) {
        $display_preference->add([
            'itemtype' => $itemtype,
            'num'      => (int) $preference['num'],
            'rank'     => (int) $preference['rank'],
            'users_id' => 0
        ]);
    }
}

function plugin_kbrenaming_install() {

    global $DB;

    if (!plugin_kbrenaming_is_command_line()) {
        Html::header(__('Setup'), filter_input(INPUT_SERVER, "PHP_SELF"), "config", "plugins");
    }

    $migration = new Migration(PLUGIN_KBRENAMING_VERSION);
    $migration->displayMessage("creation Table in db ");
    $default_charset = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();

    //Create table only if it does not exists yet!
    if (!$DB->tableExists('glpi_plugin_kbrenaming_kbs')) {
        //table creation query
        $query = "CREATE TABLE `glpi_plugin_kbrenaming_kbs` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `comment` text COLLATE utf8_unicode_ci DEFAULT NULL,
                  `plugin_kbrenaming_kbgroups_id` int(11) NOT NULL DEFAULT 0,
                  `disabled_update` tinyint(1) NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `name_UNIQUE` (`name`),
                  KEY `name` (`name`),
                  KEY `plugin_kbrenaming_kbgroups_id` (`plugin_kbrenaming_kbgroups_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        $DB->queryOrDie($query, $DB->error());
    } else {
        $migration->addField('glpi_plugin_kbrenaming_kbs', 'disabled_update', 'bool', ['value' => 0]);
        $migration->addKey('glpi_plugin_kbrenaming_kbs', 'name');
        $migration->addKey('glpi_plugin_kbrenaming_kbs', 'plugin_kbrenaming_kbgroups_id');
    }

    //Create table only if it does not exists yet!
    if (!$DB->tableExists('glpi_plugin_kbrenaming_kbgroups')) {
        //table creation query
        $query = "CREATE TABLE `glpi_plugin_kbrenaming_kbgroups` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `comment` text COLLATE utf8_unicode_ci DEFAULT NULL,
                  `softwarecategories_id` int(11) NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `name_UNIQUE` (`name`),
                  KEY `name` (`name`),
                  KEY `softwarecategories_id` (`softwarecategories_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation";
        $DB->queryOrDie($query, $DB->error());
    } else {
        $migration->addField('glpi_plugin_kbrenaming_kbgroups', 'softwarecategories_id', 'integer', ['value' => 0]);
        $migration->addKey('glpi_plugin_kbrenaming_kbgroups', 'name');
        $migration->addKey('glpi_plugin_kbrenaming_kbgroups', 'softwarecategories_id');
    }

    // add display preferences
    plugin_kbrenaming_add_display_preferences('PluginKbrenamingKb', [
        ['num' => 3002, 'rank' => 1],
        ['num' => 16, 'rank' => 2],
        ['num' => 3003, 'rank' => 3],
    ]);
    plugin_kbrenaming_add_display_preferences('PluginKbrenamingKbGroup', [
        ['num' => 3001, 'rank' => 1],
        ['num' => 16, 'rank' => 2],
    ]);

    //execute the whole migration
    $migration->executeMigration();

    return true;
}


/**
* Manage the uninstallation of the plugin
*
* @return boolean
*/
function plugin_kbrenaming_uninstall() {
    global $DB;

    $tables = [
        'kbs',
        'kbgroups'
    ];

    foreach ($tables as $table) {
        $tablename = 'glpi_plugin_kbrenaming_' . $table;
        //Create table only if it does not exists yet!
        if ($DB->tableExists($tablename)) {
            $DB->queryOrDie(
                "DROP TABLE `$tablename`",
                $DB->error()
            );
        }
    }

    // clean display preferences
    $pref = new DisplayPreference;
    $pref->deleteByCriteria([
        'itemtype' => ['LIKE' , 'PluginKbrenaming%']
    ]);
    return true;
}

function plugin_item_add_update_kbrenaming(Software $parm): Software {
    Toolbox::logDebug('-------------------- Start item_add/update : '. get_class($parm) .'--------------------');
    $kbName = trim((string) ($parm->fields['name'] ?? ''));
    if (!plugin_kbrenaming_is_kb_name($kbName)) {
        return $parm;
    }
    Toolbox::logDebug('$parm : ' . print_r($parm, true));
    Toolbox::logDebug('$kbName : ' . $kbName);
    Toolbox::logDebug('$parm : ' . print_r($parm, true));
    $kb = new PluginKbrenamingKb();
    $kbData = $kb->getByName($kbName);
    if ($kbData === false){
        return $parm;
    }
    $old_field = $parm->fields;
    Toolbox::logDebug('$kbData : ' . print_r($kbData, true));
    $software = new Software();
    $kb_group = $kbData->fields['plugin_kbrenaming_kbgroup'] ?? [];
    $kb_group_name = trim((string) ($kb_group['name'] ?? ''));
    if ($kb_group_name === '') {
        return $parm;
    }

    $condition = ['name' => $kb_group_name];
    Toolbox::logDebug('$condition : ' . print_r($condition, true));
    $softs = $software->find($condition, [], 1);
    Toolbox::logDebug('$soft : ' . print_r($softs, true));
    if (empty($softs)){

        $manufacturer_db = new Manufacturer();
        $input_manufacturer = ['name' => 'Microsoft'];
        $manufacturers_id = $manufacturer_db->findID($input_manufacturer);
        if ($manufacturers_id < 0) {
            $manufacturers_id = $manufacturer_db->import($input_manufacturer);
        }

        $input = $kb_group;
        unset($input['id']);
        unset($input['softwarecategory']);
        $input['entities_id'] = (int) ($old_field['entities_id'] ?? 0);
        $input['is_recursive'] = 1;
        $input['manufacturers_id'] = $manufacturers_id;
        $soft_id = $software->add($input);
        if (!$soft_id) {
            return $parm;
        }
    }else{
        $soft = array_shift($softs);
        $soft_id = (int) $soft['id'];
        $software->getFromDB($soft_id);
    }
    $parm->fields = $software->fields;

    $operatingsystem_db = new OperatingSystem();
    $input_operatingsystem = ['name' => 'Windows'];
    $operatingsystems_id = $operatingsystem_db->findID($input_operatingsystem);
    if ($operatingsystems_id < 0) {
        $operatingsystems_id = $operatingsystem_db->import($input_operatingsystem);
    }

    $softwareversion = new SoftwareVersion();
    $condition = ['name' => $kbData->fields['name']];
    $soft_versions = $softwareversion->find($condition,[],1);
    if (empty($soft_versions)) {
        $input = [
            'name' => $kbData->fields['name'],
            'comment' => $kbData->fields['comment'],
            'entities_id' => (int) ($old_field['entities_id'] ?? 0),
            'is_recursive' => 1,
            'softwares_id' => $soft_id,
            'operatingsystems_id' => $operatingsystems_id
        ];
        $soft_version_id = $softwareversion->add($input);
    }else{
        $soft_version = array_shift($soft_versions);
        $soft_version_id = $soft_version['id'];
        $softwareversion->getFromDB($soft_version_id);
    }
    if ($soft_version_id>0){
        $condition = ['softwares_id' => (int) ($old_field['id'] ?? 0)];
        $softwareversions =  $softwareversion->find($condition);
        foreach ($softwareversions as $id => $softwareversion){
            PluginKbrenamingToolbox::change_softwareversion((int) $id, (int) $soft_version_id);
        }
    }
    $old_software_id = (int) ($old_field['id'] ?? 0);
    $new_software_id = (int) ($parm->fields['id'] ?? 0);
    if ($old_software_id > 0 && $old_software_id !== $new_software_id ){
        $result = PluginKbrenamingToolbox::deleteSoftwareVersionsBySoftwareId($old_software_id);
        if($result!== false){
            $condition = ['id' => $old_software_id];
            $software->delete($condition);
        }
    }
    return $parm ;
}

function plugin_post_item_form_kbrenaming($params){
    if (isset($params['item']) && $params['item'] instanceof Software) {
        Toolbox::logDebug('-------------------- Start post_item_form : '. get_class($params['item']) .'--------------------');
        $software = $params['item'];
        $software_name = trim((string) ($software->fields['name'] ?? ''));
        if ((int) ($software->fields['is_deleted'] ?? 0) === 1 && plugin_kbrenaming_is_kb_name($software_name)){
            $softwareversion = new SoftwareVersion();
            $condition = ['name' => $software_name];
            $soft_versions = $softwareversion->find($condition,[],1);
            if (!empty($soft_versions)){
                $soft_version = array_shift($soft_versions);
                if(!empty($soft_version['softwares_id'])) {
                    $condition = ['id' => (int) ($software->fields['id'] ?? 0)];
                    $software->delete($condition, true);
                    Html::redirect($software->getFormURLWithID($soft_version['softwares_id']));
                }
            }

        }

    }
}

/**
 * Extra MMODEL and ENVS and copy in msf section in inventory
 *
 * @params object $parms
 * @return object
 */
function plugin_fusioninventory_addinventoryinfos_kbrenaming($params = []){
    Toolbox::logDebug('-------------------- Start plugin_fusioninventory_addinventoryinfos_kbrenaming : --------------------');
    if (empty($params['inventory']['SOFTWARES']) || !is_array($params['inventory']['SOFTWARES'])) {
        return $params;
    }

    foreach ($params['inventory']['SOFTWARES'] as &$software){
        if (!is_array($software)) {
            continue;
        }
        $kbName = trim((string) ($software['NAME'] ?? ''));
        if (!plugin_kbrenaming_is_kb_name($kbName)) {
            continue;
        }
        $kb = new PluginKbrenamingKb();
        $kbData = $kb->getByName($kbName);
        if ($kbData === false){
            continue;
        }
        $kb_group = $kbData->fields['plugin_kbrenaming_kbgroup'] ?? [];
        $kb_group_name = trim((string) ($kb_group['name'] ?? ''));
        if ($kb_group_name === '') {
            continue;
        }
        $software['COMMENTS'] = (string) ($kbData->fields['comment'] ?? '');
        $software['NAME'] = $kb_group_name;
        $software['VERSION'] = $kbName;
        $software['PUBLISHER'] = 'Microsoft';
        $software['SYSTEM_CATEGORY'] = (string) ($kb_group['softwarecategory']['name'] ?? '');
    }
    return $params;
}
/**
 * Define Dropdown tables to be manage in GLPI
 *
 * @return array
 */
function plugin_kbrenaming_getDropdown()
{
    //    error_log('function plugin_kbrenaming_getDropdown');
    $plugin = new Plugin();


    if ($plugin->isActivated(PLUGIN_KBRENAMING_ID)) {
        return [
            'PluginKbrenamingKb' => PluginKbrenamingKb::getTypeName(2),
            'PluginKbrenamingKbGroup' => PluginKbrenamingKbGroup::getTypeName(2),
        ];
    } else {
        return [];
    }
}

// Define dropdown relations
function plugin_kbrenaming_getDatabaseRelations() {
    $plugin = new Plugin();

    if ($plugin->isActivated(PLUGIN_KBRENAMING_ID)) {
        return ["glpi_plugin_kbrenaming_kbgroups" => ["glpi_plugin_kbrenaming_kbs" => "plugin_kbrenaming_kbgroups_id"],
            "glpi_softwarecategories" => ["glpi_plugin_kbrenaming_kbgroups" => "softwarecategories_id"]];
    } else {
        return [];
    }
}
