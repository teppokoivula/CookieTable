<?php namespace ProcessWire;

class CookieTable extends WireData implements Module {

	/**
	 * Get module information
	 *
	 * @return array
	 */
	public static function getModuleInfo() {
		return [
			'title' => 'Cookie Table',
			'summary' => 'A module for providing a cookie table for a ProcessWire website.',
			'version' => '0.0.1',
			'author' => 'Teppo Koivula',
			'href' => 'https://github.com/teppokoivula/cookie-table',
			'icon' => 'certificate',
			'autoload' => false,
			'requires' => 'PHP>=8.0, ProcessWire>=3.0.184',
			'installs' => 'ProcessCookieTable',
			'singular' => true,
		];
	}

	/**
	 * Get cookies from database
	 *
	 * @return array
	 */
	public function getCookies(): array {
		$stmt = $this->database->prepare("
		SELECT ct.*, cc.name AS category_name, cc.label AS category_label
		FROM `cookie_table` ct
		LEFT JOIN `cookie_table_categories` cc
		ON ct.category_id = cc.id
		ORDER BY ct.name ASC
		");
		$stmt->execute();
		$cookies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $cookies ?: [];
	}

	/**
	 * Render cookies as a HTML table
	 *
	 * @return string
	 */
	public function render(): string {
		$cookies = $this->getCookies();
		$table = '<table class="cookie-table">';
		$table .= '<tr>';
		$table .= '<th>' . $this->_('Cookie name') . '</th>';
		$table .= '<th>' . $this->_('Category') . '</th>';
		$table .= '<th>' . $this->_('Description') . '</th>';
		$table .= '<th>' . $this->_('Duration') . '</th>';
		$table .= '</tr>';
		foreach ($cookies as $cookie) {
			$table .= '<tr>';
			$table .= '<td>' . $cookie['name'] . '</td>';
			$table .= '<td>' . ($cookie['category_label'] ?? $cookie['category_name']) . '</td>';
			$table .= '<td>' . $cookie['description'] . '</td>';
			$table .= '<td>' . $cookie['duration'] . '</td>';
			$table .= '</tr>';
		}
		$table .= '</table>';
		return $table;
	}

	/**
	 * Get cookie by ID from database
	 *
	 * @param int $id
	 * @return array|null
	 */
	public function getCookieByID(int $id): ?array {
		$stmt = $this->database->prepare("
		SELECT ct.*, cc.name AS category_name
		FROM `cookie_table` ct
		LEFT JOIN `cookie_table_categories` cc
		ON ct.category_id = cc.id
		WHERE ct.id = ?
		");
		$stmt->execute([$id]);
		$cookie = $stmt->fetch(\PDO::FETCH_ASSOC);
		return $cookie ?: null;
	}

	/**
	 * Get cookie categories from database
	 *
	 * @return array
	 */
	public function getCookieCategories(): array {
		$stmt = $this->database->prepare("
		SELECT cc.*
		FROM `cookie_table_categories` cc
		");
		$stmt->execute();
		$categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $categories ?: [];
	}

	/**
	 * Add cookie category
	 *
	 * @param int|null $id
	 * @param string $name
	 * @param string|null $label
	 * @param string|null $description
	 * @return int Cookie category ID
	 */
	public function saveCookieCategory(?int $id, string $name, ?string $label = null, ?string $description = null): int {
		$stmt = $this->database->prepare("
		INSERT INTO `cookie_table_categories` (`id`, `name`, `label`, `description`)
		VALUES (?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE name=VALUES(name), label=VALUES(label), description=VALUES(description)
		");
		$stmt->execute([
			$id,
			$name,
			$label,
			$description,
		]);
		return $id === null ? $this->database->lastInsertId() : $id;
	}

	/**
	 * Save cookie (add or update)
	 *
	 * @param int|null $id
	 * @param string $name
	 * @param string|null $provider
	 * @param string|null $duration
	 * @param int|null $category_id
	 * @param string|null $description
	 * @param string|null $metadata
	 * @return int Cookie ID
	 *
	 * @throws WireException if provided ID is invalid
	 */
	public function saveCookie(?int $id, string $name, ?string $provider = null, ?string $duration = null, ?int $category_id = null, ?string $description = null, ?string $metadata = null): int {
		if ($id === 0 || $id < 0) {
			throw new WireException(sprintf(
				$this->_('Invalid ID provided (%d), must be null or an integer that is greater than zero'),
				$id
			));
		}
		$stmt = $this->database->prepare("
		INSERT INTO `cookie_table` (`id`, `name`, `provider`, `duration`, `category_id`, `description`, `metadata`)
		VALUES (?, ?, ?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE name=VALUES(name), provider=VALUES(provider), duration=VALUES(provider), duration=VALUES(duration), category_id=VALUES(category_id), description=VALUES(description), metadata=VALUES(metadata)
		");
		$stmt->execute([
			$id,
			$name,
			$provider,
			$duration,
			$category_id,
			$description,
			$metadata,
		]);
		return $id === null ? $this->database->lastInsertId() : $id;
	}

	/**
	 * Delete cookie by ID
	 *
	 * @param int $id
	 * @return bool True if cookie was deleted, false otherwise
	 */
	public function deleteCookieByID(int $id): bool {
		$stmt = $this->database->prepare("
		DELETE FROM `cookie_table` WHERE `id` = ? LIMIT 1
		");
		$stmt->execute([$id]);
		return $stmt->rowCount() > 0;
	}

	/**
	 * Tasks to run when installing the module
	 */
	public function ___install() {

		// create table for cookie categories
		$sql = "
		CREATE TABLE IF NOT EXISTS `cookie_table_categories` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(128) NOT NULL UNIQUE,
			`label` VARCHAR(128),
			`description` text,
			`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`)
		) ENGINE=" . $this->config->dbEngine . " DEFAULT CHARSET=" . $this->config->dbCharset . ";
		";
		$this->database->exec($sql);
		$this->message("Created table 'cookie_table_categories'");

		// add default cookie categories
		$this->saveCookieCategory(null, 'necessary', 'Necessary');
		$this->saveCookieCategory(null, 'functional', 'Functional');
		$this->saveCookieCategory(null, 'preferences', 'Preferences');
		$this->saveCookieCategory(null, 'statistics', 'Statistics');
		$this->saveCookieCategory(null, 'marketing', 'Marketing');

		// create table for cookies
		$sql = "
		CREATE TABLE IF NOT EXISTS `cookie_table` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`name` text NOT NULL,
			`provider` VARCHAR(256),
			`duration` VARCHAR(256),
			`category_id` int(11),
			`description` text,
			`metadata` text,
			`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`)
		) ENGINE=" . $this->config->dbEngine . " DEFAULT CHARSET=" . $this->config->dbCharset . ";
		";
		$this->database->exec($sql);
		$this->message("Created table 'cookie_table'");

		// add default cookies
		$this->saveCookie(
			null,
			'wires',
			null,
			'First-party session cookie, expires when the browser is closed.',
			1,
			'ProcessWire session identifier.',
			null
		);
		$this->saveCookie(
			null,
			'wires_challenge',
			null,
			'First-party persistent cookie, expires after 30 days.',
			1,
			'ProcessWire session cookie used to verify the validity of a session.',
			null
		);
	}

	/**
	 * Tasks to run when uninstalling the module
	 */
	public function ___uninstall() {
		$this->database->exec("DROP TABLE IF EXISTS `cookie_table`");
		$this->message("Dropped table 'cookie_table'");
		$this->database->exec("DROP TABLE IF EXISTS `cookie_table_categories`");
		$this->message("Dropped table 'cookie_table_categories'");
	}
}
