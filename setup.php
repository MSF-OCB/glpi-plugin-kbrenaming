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

use Glpi\Plugin\Hooks;

define ("PLUGIN_KBRENAMING_VERSION", "2.1.0");
define ("PLUGIN_KBRENAMING_ID", "kbrenaming");
// Minimal GLPI version, inclusive
define('PLUGIN_KBRENAMING_GLPI_MIN_VERSION', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_KBRENAMING_GLPI_MAX_VERSION', '12.0.0');

define ("PLUGIN_KBRENAMING_OFFICIAL_RELEASE", "1");
define ("PLUGIN_KBRENAMING_REALVERSION", PLUGIN_KBRENAMING_VERSION . "");
define ("PLUGIN_KBRENAMING_PROCEDURE", "glpi_plugin_kbrenaming");

/**
 * Init the hooks of MSF
 *
 * @global array $PLUGIN_HOOKS
 * @global array $CFG_GLPI
 */
function plugin_init_kbrenaming() {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT][PLUGIN_KBRENAMING_ID] = true;

    $Plugin = new Plugin();
    $moduleId = 0;

    $debug_mode = true;
    if (isset($_SESSION['glpi_use_mode'])) {
      $debug_mode = ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE);
    }
    if ($Plugin->isInstalled('kbrenaming')
       && $Plugin->isActivated('kbrenaming')) { // check if plugin is active

        Plugin::registerClass('PluginKbrenamingKb');
        Plugin::registerClass('PluginKbrenamingKbGroup');
        $PLUGIN_HOOKS['fusioninventory_addinventoryinfos'][PLUGIN_KBRENAMING_ID] = 'plugin_fusioninventory_addinventoryinfos_kbrenaming';


        $PLUGIN_HOOKS[Hooks::ITEM_ADD][PLUGIN_KBRENAMING_ID] = [
            Software::class   => 'plugin_item_add_update_kbrenaming'
        ];
        $PLUGIN_HOOKS[Hooks::ITEM_UPDATE][PLUGIN_KBRENAMING_ID] = [
            Software::class   => 'plugin_item_add_update_kbrenaming'
        ];
        $PLUGIN_HOOKS['post_item_form'][PLUGIN_KBRENAMING_ID] =  'plugin_post_item_form_kbrenaming';

        $report_list=[];

        if (Session::haveRight('software', READ)) {
            $report_list["report/kb_entities_osversion.php"] = __('Summaries numbers computer by entries by OS version for one KB', PLUGIN_KBRENAMING_ID);
        }
        $PLUGIN_HOOKS['reports'][PLUGIN_KBRENAMING_ID] = $report_list;

    }
}


/**
 * Manage the version information of the plugin
 *
 * @return array
 */
function plugin_version_kbrenaming() {
   return ['name'           => 'kb Software',
           'shortname'      => PLUGIN_KBRENAMING_ID,
           'version'        => PLUGIN_KBRENAMING_VERSION,
           'license'        => 'AGPLv3+',
           'author'         => '<a href="mailto:sebastien.batteur@brussels.msf.org">SEBASTIEN BATTEUR</a>',
           'homepage'       => 'https://github.com/msf-ocb/glpi-plugin-kbrenaming',
           'description'    => __('Normalizes Microsoft KB software inventory entries by grouping KB patches under their software update family, creating software versions, and enriching inventory data from the Microsoft Update Catalog.', PLUGIN_KBRENAMING_ID),
           'requirements'   => [
              'glpi' => [
                  'min' => PLUGIN_KBRENAMING_GLPI_MIN_VERSION,
                  'max' => PLUGIN_KBRENAMING_GLPI_MAX_VERSION,
                  'dev' => PLUGIN_KBRENAMING_OFFICIAL_RELEASE == 0
               ]
            ]
         ];
}


/**
 * Manage / check the prerequisites of the plugin
 *
 * @return boolean
 */
function plugin_kbrenaming_check_prerequisites(){

   return true;
}


/**
 * Check if the config is ok
 *
 * @return boolean
 */
function plugin_kbrenaming_check_config() {
   return true;
}


/**
 * Check the rights
 *
 * @param string $type
 * @param string $right
 * @return boolean
 */
function plugin_kbrenaming_haveTypeRight($type, $right) {
   return true;
}
