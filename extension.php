<?php

class NtfyExtension extends Minz_Extension {

	private $cache = [];

	private $debug = false;

	public function init(): void {
		parent::init();

		$this->registerTranslates();
		$this->registerHook('entry_before_insert', [$this, 'newEntry']);
		$this->registerHook('js_vars', [$this, 'jsVars']);
		register_shutdown_function([$this, 'shutdownHandler']);

		Minz_View::appendScript($this->getFileUrl('ntfy.js'),'','','');
	}

	public function handleConfigureAction(): void {
		if (Minz_Request::isPost()) {
			if (Minz_Request::paramStringNull('ntfy') === "feed") {
				$this->saveFeed();
			}
			else {
				$this->saveConfig();
			}
		}
	}

	private function saveConfig(): void {
		$config = $this->getUserConfiguration();

		$config['server'] = trim(trim(Minz_Request::paramString("server")), '/');
		$config['default_topic'] = trim(trim(Minz_Request::paramString("default_topic")), '/');
		$config['aggregate'] = Minz_Request::paramBoolean("aggregate");

		$this->setUserConfiguration($config);
	}

	private function saveFeed(): void {
		$feedId = Minz_Request::paramIntNull('feed_id');
		$topic = Minz_Request::paramStringNull('topic');
		$topic = trim(trim($topic), '/');

		$config = $this->getUserConfiguration();
		$config['feeds'][$feedId] = [
			'topic' => $topic,
		];
		$this->setUserConfiguration($config);
	}

	public function newEntry(FreshRSS_Entry $entry): FreshRSS_Entry {
		$feed = $entry->feed();

		if ($entry->isUpdated()) return $entry;

		if(!isset($this->cache[$feed->id()])) $this->cache[$feed->id()] = 0;
		$this->cache[$feed->id()] += 1;

		return $entry;
	}

	public function shutdownHandler(): void {
		if(empty($this->cache)) return;

		$config = $this->getUserConfiguration();
		$server = $config['server'] ?? null;
		$defaultTopic = $config['default_topic'] ?? null;

		if (!$server || !$defaultTopic) return;

		$feedDAO = FreshRSS_Factory::createFeedDao();
		$total = 0;

		foreach ($this->cache as $feedId => $feedCount) {
			$feed = $feedDAO->searchById($feedId);
			$topic = $config['feeds'][$feedId]['topic'] ?? null;
			if ($topic === null) {
				if ($config['aggregate']) {
					$total += $feedCount;
					continue;
				}
				$topic = $defaultTopic;
			}
			$feedName = $feed->name();
			$this->sendNotification("$server/$topic", "'$feedName' has $feedCount new article(s)");
		}

		if($config['aggregate'] && $total > 0) {
			$this->sendNotification("$server/$defaultTopic", "Your feeds have $feedCount new article(s)");
		}
	}

	private function sendNotification(string $url, string $content) {
		file_get_contents($url, false, stream_context_create([
			'http' => [
				'method' => 'POST', // PUT also works
				'header' => 'Content-Type: text/plain',
				'content' => $content,
			]
		]));
	}

	public function jsVars(array $vars): array {
		$vars['ntfy_ext_name'] = $this->getName();
		$vars['ntfy_feed_config_html'] = file_get_contents(__DIR__ . '/static/feed_config.html');
		$vars['ntfy_feeds'] = $this->getUserConfiguration()['feeds'] ?? [];
		return $vars;
	}

	function extensionLog(string $data) {
		if (!$this->debug) return;
		syslog(LOG_INFO, "ntfy: " . $data);
	}
}
