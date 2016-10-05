<?php namespace App;

vimport('~/modules/com_vtiger_workflow/VTJsonCondition.inc');
vimport('~/modules/com_vtiger_workflow/VTEntityCache.inc');
vimport('~/include/Webservices/Retrieve.php');

/**
 * Advanced privilege class
 * @package YetiForce.App
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class PrivilegeAdv
{

	protected static $cacheFile = 'user_privileges/advancedPermission.php';
	protected static $cache = false;

	/**
	 * Update advanced permissions cache.
	 */
	public static function reloadCache()
	{
		$db = \App\DB::getInstance('admin');
		$query = (new \App\db\Query())->from('a_#__adv_permission')->where(['status' => 0]);
		$dataReader = $query->createCommand($db)->query();
		$cache = [];
		while ($row = $dataReader->read()) {
			$cache[(int) $row['tabid']][] = ['action' => (int) $row['action'], 'conditions' => $row['conditions']];
		}
		$content = '<?php return ' . \vtlib\Functions::varExportMin($cache) . ';' . PHP_EOL;
		file_put_contents(static::$cacheFile, $content, LOCK_EX);
	}

	/**
	 * Load advanced permission rules for specific module
	 * @param string $moduleName
	 * @return array
	 */
	public static function get($moduleName)
	{
		if (static::$cache === false) {
			static::$cache = require static::$cacheFile;
		}
		$tabid = \includes\Modules::getModuleId($moduleName);
		return isset(static::$cache[$tabid]) ? static::$cache[$tabid] : false;
	}

	/**
	 * Check advanced permissions
	 * @param int $record
	 * @param string $moduleName
	 * @return boolean|int
	 */
	public static function checkPermissions($record, $moduleName)
	{
		$privileges = static::get($moduleName);
		if ($privileges === false) {
			return false;
		}
		$currentUser = \Users_Record_Model::getCurrentUserModel();
		$permission = false;
		foreach ($privileges as &$privilege) {
			$entityCache = new \VTEntityCache($currentUser);
			$wsId = vtws_getWebserviceEntityId($moduleName, $record);
			$test = (new \VTJsonCondition())->evaluate($privilege['conditions'], $entityCache, $wsId);
			if ($test) {
				$permission = $privilege['action'] === 0 ? 1 : 0;
			}
		}
		return $permission;
	}
}