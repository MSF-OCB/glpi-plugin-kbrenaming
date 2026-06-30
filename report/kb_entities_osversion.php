<?php
/**
 *
 * software_entities_osversion.php
 *
 *
 *
 * @version GIT: $Id$
 * @author  Sébastien Batteur <sebastien.batteur@brussels.msf.org>
 */
$USEDBREPLICATE=1;
$DBCONNECTION_REQUIRED=0;
$UNKNOWN='Unknown';
$TOTAL="Total";
include ("../../../inc/includes.php");

Session::checkRight("computer", READ);

if (!function_exists('plugin_kbrenaming_report_escape')) {
    function plugin_kbrenaming_report_escape($value): string
    {
        return Html::entities_deep((string) $value);
    }
}

Html::header(
    __('Kb - ', 'kbrenaming') . __('Summeries numbers computer by entries by OS version for one KB', 'kbrenaming'),
    filter_input(INPUT_SERVER, "PHP_SELF"),
    "utils",
    "report"
);

Report::title();

$app_name = trim((string) filter_input(INPUT_GET, "app_name"));

$entities_id = filter_input(INPUT_GET, "entities_id", FILTER_VALIDATE_INT);
if ($entities_id === false || $entities_id === null) {
    $entities_id = (int) $_SESSION['glpiactive_entity'];
}
echo "<form action='".plugin_kbrenaming_report_escape(filter_input(INPUT_SERVER, "PHP_SELF"))."' method='get'>";
echo "<table class='tab_cadre_fixe' cellpadding='2'>";
echo "<tr><th colspan='2' class=''>". __('Summeries numbers computer by entries by OS version for one KB', 'msf') ."</th></tr>";
echo "<tr class='tab_bg_1' align='center'>";
echo "<td>";
echo __('kb name');
echo "</td>";
echo "<td>";
echo Html::input('app_name', [
    'value' => $app_name,
    'required'=> 'required'
]);
//Dropdown::show("PluginFusioninventoryTask", array('name'=>'Task', 'value'=>$tasks, 'entity_sons' = True));
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1' align='center'>";
echo "<td>";
echo __('Entity');
echo "</td>";
echo "<td>";
Dropdown::show("Entity", array( 'value'=>$entities_id));
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td align='center' colspan='2'>";
echo "<input type='submit' value='" . __('Validate') . "' class='submit' />";
echo "</td>";
echo "</tr>";

echo "</table>";
Html::closeForm();


if (empty($app_name)){
    Html::footer();
    exit;
}
$app_name_sql = $app_name[0]!='^'?'^'.$app_name:$app_name;
$app_name_sql = strlen($app_name_sql) > 0 && $app_name_sql[strlen($app_name_sql)-1]!='$'?$app_name_sql.'$':$app_name_sql;
if (stripos( $app_name_sql, "^kb" ) !== 0){
    Html::footer();
    exit;
}
$entities_sql = '';
if ($entities_id > 0){
    $entities_sql = ' AND `glpi_computers`.`entities_id` IN ('.implode(',', getSonsOf('glpi_entities', $entities_id)).') ';
}

$query = "SELECT 
    `glpi_operatingsystemversions`.`id` AS operatingsystemversions_id,
    `glpi_operatingsystemversions`.`name` AS operatingsystemversions_name,
    `glpi_computers`.`entities_id`,
    `glpi_entities`.`completename` AS entities_name,
    `glpi_softwares`.`id` AS softwares_id,
    `glpi_softwares`.`name` AS softwares_name,
    `glpi_softwareversions`.`id` AS softwareversions_id,
    COUNT(*) AS total
FROM
    `glpi_softwares`
        INNER JOIN
    `glpi_softwareversions` ON `glpi_softwareversions`.`softwares_id` = `glpi_softwares`.`id`
        INNER JOIN
    `glpi_items_softwareversions` ON (`glpi_items_softwareversions`.`softwareversions_id` = `glpi_softwareversions`.`id`)
        INNER JOIN
    `glpi_computers` ON (`glpi_computers`.`id` = `glpi_items_softwareversions`.`items_id`
        AND `glpi_items_softwareversions`.`itemtype` = 'Computer')
        INNER JOIN
    `glpi_items_operatingsystems` ON (`glpi_items_operatingsystems`.`items_id` = `glpi_computers`.`id`
        AND `glpi_items_operatingsystems`.`itemtype` = 'Computer')
        LEFT JOIN
    `glpi_operatingsystemversions` ON (`glpi_items_operatingsystems`.`operatingsystemversions_id` = `glpi_operatingsystemversions`.`id`)
        LEFT JOIN
    `glpi_entities` ON `glpi_entities`.`id` = `glpi_computers`.`entities_id`
WHERE
    (`glpi_softwares`.`name` " . Search::makeTextSearch($app_name_sql) . "
        OR `glpi_softwareversions`.name " . Search::makeTextSearch($app_name_sql) . ")        
        ". $entities_sql . "
        AND `glpi_items_softwareversions`.`is_deleted` = '0'
        AND `glpi_computers`.`is_deleted` = '0'
        AND `glpi_computers`.`is_template` = '0'
GROUP BY `glpi_computers`.`entities_id` , `glpi_operatingsystemversions`.`id`, `glpi_softwares`.`id`
ORDER BY `glpi_softwares`.`name`, `glpi_computers`.`entities_id` ;";

$result = $DB->query($query);
$datas = [];
$os_versions = [];
$nb_items = 0;

while ($data=$DB->fetchArray($result)) {
    $data_key = $data['entities_id']."|".$data['softwares_id']."|".$data['softwareversions_id'];
    if (!array_key_exists($data_key, $datas)){
        $datas[$data_key] = [
            -1 => 0,
            'entities_name' => $data['entities_name'],
            'softwares_name' => $data['softwares_name'],
            'entities_id' => $data['entities_id'],
            'softwares_id' => $data['softwares_id'],
            'softwareversions_id' => $data['softwareversions_id']
        ];
    }
    if (!array_key_exists($data['operatingsystemversions_id'], $os_versions)){
        $os_versions[$data['operatingsystemversions_id']] = [
            'label'=>$data['operatingsystemversions_name'],
            'value'=> 0
        ];
    }
    $datas[$data_key][$data['operatingsystemversions_id']] = $data['total'];
    $datas[$data_key][-1] += $data['total'];
    $os_versions[$data['operatingsystemversions_id']]['value'] += $data['total'];
    $nb_items += $data['total'];
}

if (empty($datas)){
    Html::footer();
    exit;
}

uasort($os_versions,function ($a, $b) {
    return strcmp($a['label'], $b['label']);
});
$os_versions [-1]= [
    'label'=>$TOTAL,
    'value'=> $nb_items
];

$query = "SELECT 
    `glpi_operatingsystemversions`.`id` AS operatingsystemversions_id,
    `glpi_operatingsystemversions`.`name` AS operatingsystemversions_name,
    `glpi_computers`.`entities_id`,
    COUNT(*) AS total
FROM
    `glpi_computers` 
        INNER JOIN
    `glpi_items_operatingsystems` ON (`glpi_items_operatingsystems`.`items_id` = `glpi_computers`.`id`
        AND `glpi_items_operatingsystems`.`itemtype` = 'Computer')
        LEFT JOIN
    `glpi_operatingsystemversions` ON (`glpi_items_operatingsystems`.`operatingsystemversions_id` = `glpi_operatingsystemversions`.`id`)
WHERE
        `glpi_computers`.`is_deleted` = '0'
        AND `glpi_computers`.`is_template` = '0'
GROUP BY `glpi_computers`.`entities_id` , `glpi_operatingsystemversions`.`id` ;";
$result = $DB->query($query);
$totals = [];
while ($data=$DB->fetchArray($result)) {
    if (isset($os_versions[$data['operatingsystemversions_id']])){
        if (!isset($totals[$data['entities_id']])){
            $totals[$data['entities_id']] = [];
        }
        if (!isset($totals[$data['entities_id']][-1])){
            $totals[$data['entities_id']][-1] = 0;
        }
        $totals[$data['entities_id']][$data['operatingsystemversions_id']] = $data['total'];
        $totals[$data['entities_id']][-1] += $data['total'];
    }
}


echo "<table class='tab_cadrehov' >";
echo '<thead>';
echo '<tr class="tab_bg_1">';
echo "<th colspan='" . (count( $os_versions) + 2) . "'>".__('Number of items')." : ".count($datas)."</th>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
// echo "<th>".__('Software name')."</th>";
echo "<th>".__('Entity')."</th>";
foreach ($os_versions as $key => $value){
    echo "<th>".plugin_kbrenaming_report_escape(__($value['label']))."</th>";
}
echo "</tr>";
echo '</thead>';
echo '<tbody>';

$software = new Software();
$softwareversion = new SoftwareVersion();

$search_options['field']      = 1; // name
$search_options['searchtype'] = 'contain';
$search_options['value']      = '';
$search_options['link']       = 'AND';

$search_options_entity['field']      = 80; // entity
$search_options_entity['searchtype'] = 'equals';
$search_options_entity['value']      = 0;
$search_options_entity['link']       = 'AND';

$criteria = [
    'is_deleted' => 0,
    'as_map' => 0
];
$criteria['criteria'][0] = $search_options;

$i = 0;
foreach ($datas as $data)    {
    echo "<tr class='tab_bg_" . (1 + ($i % 2)) ."'>";
/*    echo "<td>";
    $criteria['criteria'][0]['value']      = ($data['softwares_name']== ''?"":"^" . $data['softwares_name'] ."$") ;
    echo "<a href=\"".$software->getSearchURL()."?".Toolbox::append_params($criteria)."\" target='_blank'>" . $data['softwares_name'] . "</a>";
    echo "</td>";*/

    if (empty($data['softwareversions_id'])){
        $software->fields = [
            'id' => $data['softwares_id'],
            'name' => $data['softwares_name']
        ];
        $software_url = $software->getLinkURL();
    }else{
        $softwareversion->fields = [
            'id' => $data['softwareversions_id']
        ];
        $software_url = $softwareversion->getLinkURL();
    }

    echo "<td>";
    echo "<a href=\"".plugin_kbrenaming_report_escape($software_url)."\" target='_blank'>" . plugin_kbrenaming_report_escape($data['entities_name']) . "</a>";
    echo "</td>";

    foreach ($os_versions as $key => $value){
        if ($key == -1){
            echo '<td style="white-space:nowrap;">';
            echo "<a href=\"".plugin_kbrenaming_report_escape($software_url)."\" target='_blank'>" . (int) $data[$key] . "</a> / " . (int) ($totals[$data['entities_id']][$key] ?? 0);
            echo "</td>";
        }else{
            echo '<td style="white-space:nowrap;">'. (isset($data[$key]) && $data[$key] > 0 ?(int) $data[$key] . " / " . (int) ($totals[$data['entities_id']][$key] ?? 0) : '&nbsp') . "</td>";        }
    }
    echo "</tr>";
    $i ++;
}
echo '</tbody>';

echo '<floter>';
echo "<tr class='tab_bg_" . (1 + ($i % 2)) ."'>";
echo "<th colspan='1'>".plugin_kbrenaming_report_escape(__($TOTAL))."</th>";
foreach ($os_versions as $key => $value){
    echo "<th>";
    echo "</td>". ($value['value'] > 0 ? (int) $value['value']: '&nbsp') . "</td>";
    echo "</th>";
}
echo "</tr>";
echo '</floter>';

echo "</table>";


Html::footer();
