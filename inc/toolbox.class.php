<?php

/**
 *
 * toolbox.class.php
 *
 *
 *
 * @version GIT: $
 * @author  Sébastien Batteur <sebastien.batteur@brussels.msf.org>
 */
class PluginKbrenamingToolbox
{
    const BITS = 1;
    const OCTETS = 2;
    private const SHMOP_KEY = 0x4c61737452657175;
    const SHMOP_SIZE = 15;
    private static ?float $lastRequest = null;

    public static function getLastRequest(): float{
        if (!function_exists('shmop_open')) {
            return self::$lastRequest ?? 0.0;
        }

        $shm_id = @shmop_open(self::SHMOP_KEY, "c", 0666, self::SHMOP_SIZE);
        if ($shm_id === false) {
            return self::$lastRequest ?? 0.0;
        }

        $shm_size = @shmop_size($shm_id);
        if ($shm_size === false || $shm_size <= 0) {
            return self::$lastRequest ?? 0.0;
        }

        $value = @shmop_read($shm_id, 0, $shm_size);
        if ($value === false) {
            return self::$lastRequest ?? 0.0;
        }

        return (float) trim($value);
    }

    public static function setLastRequest(float $now = 0.0): void{
        if ($now <= 0.0) {
            $now = microtime(true);
        }
        self::$lastRequest = $now;

        if (!function_exists('shmop_open')) {
            return;
        }

        $shm_id = @shmop_open(self::SHMOP_KEY, "c", 0666, self::SHMOP_SIZE);
        if ($shm_id === false) {
            return;
        }

        $payload = str_pad(substr((string) $now, 0, self::SHMOP_SIZE), self::SHMOP_SIZE, "\0");
        @shmop_write($shm_id, $payload, 0);
    }

    public static function change_softwareversion(int $old_id, int $new_id): bool
    {
        global $DB;
        if ($old_id <= 0 || $new_id <= 0) {
            return false;
        }
        if ($old_id === $new_id){
            return true;
        }
        return $DB->update(
            Item_SoftwareVersion::getTable(),
            ['softwareversions_id' => $new_id],
            ['softwareversions_id' => $old_id]
        );
    }

    public static function deleteSoftwareVersionsBySoftwareId(int $softwares_id): bool
    {
        global $DB;

        if ($softwares_id <= 0) {
            return false;
        }

        return $DB->delete(
            SoftwareVersion::getTable(),
            ['softwares_id' => $softwares_id]
        );
    }

    public static function str_union(string $string1, string $string2, int $master_string = 0, int $minimum_same = 0): string{
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
        if (strlen($return)<$minimum_same){
            $return ='';
        }
        $string = [$string1,$string2];
        return $return?:$string[$master_string];
    }

}
