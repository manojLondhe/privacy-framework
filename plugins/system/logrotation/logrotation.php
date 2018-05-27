<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.logrotation
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

\JLoader::import('joomla.filesystem.file');
\JLoader::import('joomla.filesystem.folder');

/**
 * Joomla! Log Rotation plugin
 *
 * Rotate the log files created by Joomla core
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgSystemLogrotation extends JPlugin
{
	/**
	 * Load plugin language files automatically
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * The log check and rotation code is triggered after the page has fully rendered.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onAfterRender()
	{
		// Get the timeout as configured in plugin parameters

		/** @var \Joomla\Registry\Registry $params */
		$cache_timeout = (int) $this->params->get('cachetimeout', 7);
		$cache_timeout = 24 * 3600 * $cache_timeout;
		$logstokeep    = (int) $this->params->get('logstokeep', 5);
		$purge         = $this->params->get('purge', false);

		// Do we need to run? Compare the last run timestamp stored in the plugin's options with the current
		// timestamp. If the difference is greater than the cache timeout we shall not execute again.
		$now  = time();
		$last = (int) $this->params->get('lastrun', 0);

		if ((abs($now - $last) < $cache_timeout))
		{
			return;
		}

		// Update last run status
		$this->params->set('lastrun', $now);

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
					->update($db->qn('#__extensions'))
					->set($db->qn('params') . ' = ' . $db->q($this->params->toString('JSON')))
					->where($db->qn('type') . ' = ' . $db->q('plugin'))
					->where($db->qn('folder') . ' = ' . $db->q('system'))
					->where($db->qn('element') . ' = ' . $db->q('logrotation'));

		try
		{
			// Lock the tables to prevent multiple plugin executions causing a race condition
			$db->lockTable('#__extensions');
		}
		catch (Exception $e)
		{
			// If we can't lock the tables it's too risky to continue execution
			return;
		}

		try
		{
			// Update the plugin parameters
			$result = $db->setQuery($query)->execute();

			$this->clearCacheGroups(array('com_plugins'), array(0, 1));
		}
		catch (Exception $exc)
		{
			// If we failed to execite
			$db->unlockTables();
			$result = false;
		}

		try
		{
			// Unlock the tables after writing
			$db->unlockTables();
		}
		catch (Exception $e)
		{
			// If we can't lock the tables assume we have somehow failed
			$result = false;
		}

		// Abort on failure
		if (!$result)
		{
			return;
		}

		// Get the log path
		$logpath = \JFactory::getApplication()->get('log_path');

		// Clean all log files in log folder 
		if ($purge)
		{
			if (!\JFolder::exists($logpath))
			{
				return;
			}

			$files = \JFolder::files($logpath, '\.php$', 1, true);

			foreach ($files as $file)
			{
				if (!\JFile::exists($file))
				{
					// Ignore it
					continue;
				}

				\JFile::delete($file);
			}

			return;
		}

		// Set the log files for Joomla! core
		$files = array(
				'deprecated.php',
				'error.php',
				'everything.php',
				'joomla_update.php',
				'upload.error.php',
				'jmodulehelper.log.php',
				'jcontroller.log.php',
				'indexer.php',
		);

		foreach ($files as $file)
		{
			if (!\JFile::exists($logpath . '/' . $file))
			{
				// Ignore it
				continue;
			}

			// Let's rotate log files
			if (\JFile::exists($logpath . '/' . $logstokeep . '.' . $file))
			{
				// Delete the oldest one
				\JFile::delete($logpath . '/' . $logstokeep . '.' . $file);
			}

			for ($i = $logstokeep; $i > 0; $i--)
			{
				if (\JFile::exists($logpath . '/' . $i . '.' . $file))
				{
					// Shift name plus one
					$next = $i + 1;
					\JFile::move($logpath . '/' . $i . '.' . $file, $logpath . '/' . $next . '.' . $file);
				}
			}

			\JFile::move($logpath . '/' . $file, $logpath . '/1.' . $file);
		}
	}

	/**
	 * Clears cache groups. We use it to clear the plugins cache after we update the last run timestamp.
	 *
	 * @param   array  $clearGroups   The cache groups to clean
	 * @param   array  $cacheClients  The cache clients (site, admin) to clean
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function clearCacheGroups(array $clearGroups, array $cacheClients = array(0, 1))
	{
		$conf = JFactory::getConfig();

		foreach ($clearGroups as $group)
		{
			foreach ($cacheClients as $client_id)
			{
				try
				{
					$options = array(
						'defaultgroup' => $group,
						'cachebase'    => $client_id ? JPATH_ADMINISTRATOR . '/cache' :
							$conf->get('cache_path', JPATH_SITE . '/cache')
					);

					$cache = JCache::getInstance('callback', $options);
					$cache->clean();
				}
				catch (Exception $e)
				{
					// Ignore it
				}
			}
		}
	}
}
