<?php
/**
 * RT Chat
 *
 * Is a plugin which adds MyBB Chat option, but instead of using Database for CRUD actions,
 * data is stored in cache, this plugin utilizes zero-database-query logic and provides data in the fastest way possible with minimal server resource usage,
 * its required to use in memory cache handlers such as redis or memcache(d)
 *
 * @package rt_chat
 * @author  RevertIT <https://github.com/revertit>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

declare(strict_types=1);

namespace rt\Chat\ChatHandler;

use rt\Chat\Core;

class AbstractChatHandler
{
	private string $errorMessage;
	private bool $errorStatus;
	protected \postParser $parser;
	protected \MyBB $mybb;
	protected \MyLanguage $lang;
	protected \DB_Base $db;

	public function __construct()
	{
		global $mybb, $lang, $db;

		// Get usual handlers
		$this->mybb = $mybb;
		$this->lang = $lang;
		$this->db = $db;

		$this->lang->load(Core::get_plugin_info('prefix'));

		$this->parser = new \postParser();

		if ($this->mybb->request_method !== 'post')
		{
			$this->error($this->lang->rt_chat_invalid_post_method);
		}
		if (!verify_post_check($this->mybb->get_input('my_post_key'), true))
		{
			$this->error($this->lang->invalid_post_code);
		}
	}

	/**
	 * Generate error function to ease errors handling
	 *
	 * @param string $error
	 * @return void
	 */
	protected function error(string $error): void
	{
		$this->errorStatus = false;
		$this->errorMessage = $error;
	}

	/**
	 * Get error data
	 *
	 * @return array|bool
	 */
	protected function getError(): array|bool
	{
		if (empty($this->errorMessage))
		{
			return false;
		}

		return [
			'status' => $this->errorStatus,
			'error' => $this->errorMessage,
		];
	}
}