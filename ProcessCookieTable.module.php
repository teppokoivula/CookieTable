<?php namespace ProcessWire;

class ProcessCookieTable extends Process implements Module {

	/**
	 * Get module info
	 */
	public static function getModuleInfo() {
		return [
			'title' => 'Process Cookie Table',
			'summary' => 'Admin features for the Cookie Table module.',
			'version' => '0.0.1',
			'author' => 'Teppo Koivula',
			'href' => 'https://github.com/teppokoivula/cookie-table',
			'icon' => 'certificate',
			'page' => [
				'name' => 'cookies',
				'parent' => 'setup',
				'title' => __('Cookies'),
			],
			'permission' => 'process-cookie-table',
			'requires' => 'PHP>=8.0, ProcessWire>=3.0, CookieTable',
		];
	}

	public function ___execute() {

		/** @var CookieTable */
		$cookie_table = $this->modules->get('CookieTable');

		// check if we are currently adding or editing a cookie
		if ($this->input->is('post')) {
			$cookie = $this->processPostRequest();
			if ($cookie && $this->input->post('delete')) {
				$this->session->message(sprintf($this->_('Cookie deleted: %s'), $cookie['name']));
			} else if ($cookie) {
				$this->session->message(sprintf($this->_('Cookie updated: %s'), $cookie['name']));
			}
			$this->session->redirect('./', false);
			unset($cookie);
		}

        // setup admin data table
        /** @var MarkupAdminDataTable */
        $table = $this->modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->headerRow([
            $this->_('Name'),
			$this->_('Provider'),
            $this->_('Duration'),
            $this->_('Category'),
            $this->_('Description'),
            $this->_('Created'),
			$this->_('Updated'),
			'',
		]);

		// add cookies to table
		foreach ($cookie_table->getCookies() as $cookie) {
			$table->row([
				$cookie['name'],
				$cookie['provider'],
				$cookie['duration'],
				$cookie['category_name'],
				$cookie['description'],
				$cookie['created'],
				$cookie['updated'],
				'<a href="./?id=' . $cookie['id'] . '">'
				. '<i class="fa fa-pencil-square" aria-label="' . sprintf($this->_('Edit cookie: %s'), $cookie['name']) . '"></i>'
				. '</a>'
				. '<form action="./" method="POST" onsubmit="return confirm(\'' . sprintf(
					$this->_('Are you sure you want to delete cookie "%s"?'),
					$cookie['name']
				 ) . '\')">'
				. $this->session->CSRF->renderInput()
				. '<input type="hidden" name="id" value="' . $cookie['id'] . '">'
				. '<input type="hidden" name="delete" value="' . $cookie['id'] . '">'
				. '<button type="submit" style="cursor: pointer; background: transparent; border: 0; padding: 0; font-size: 1em">'
				. '<i class="fa fa-minus-square" aria-label="' . sprintf($this->_('Delete cookie: %s'), $cookie['name']) . '"></i>'
				. '</button>'
				. '</form>',
			]);
		}

		// get cookie category options for later use
		$cookie_category_options = [];
		foreach ($cookie_table->getCookieCategories() as $cookie_category) {
			$cookie_category_options[$cookie_category['id']] = $cookie_category['label'] ?? $cookie_category['name'];
		}

		// add/edit cookie
		$cookie_id = $this->input->get('id', 'int');
		$cookie = $cookie_id ? $cookie_table->getCookieByID($cookie_id) : null;
		if ($cookie_id && $cookie === null) {
			$this->error($this->_('Invalid cookie ID'));
		}
		/** @var InputfieldFieldset */
		$fieldset = $this->modules->get('InputfieldFieldset');
		if ($cookie) {
			$fieldset->label = $this->_('Edit cookie');
			$fieldset->icon = 'pencil-square';
			$fieldset->add(
				/** @var InputfieldHidden */
				$this->modules->get('InputfieldHidden')
					->set('name', 'id')
					->set('value', $cookie['id'])
			);
		} else {
			$fieldset->label = $this->_('Add new cookie');
			$fieldset->icon = 'plus-circle';
			$fieldset->collapsed = Inputfield::collapsedYes;
		}
		$fieldset->add(
			/** @var InputfieldText */
			$this->modules->get('InputfieldText')
				->set('name', 'name')
				->set('label', $this->_('Name'))
				->set('required', true)
				->set('value', $cookie['name'] ?? null)
		);
		$fieldset->add(
			/** @var InputfieldText */
			$this->modules->get('InputfieldText')
				->set('name', 'provider')
				->set('label', $this->_('Provider'))
				->set('value', $cookie['provider'] ?? null)
		);
		$fieldset->add(
			/** @var InputfieldText */
			$this->modules->get('InputfieldText')
				->set('name', 'duration')
				->set('label', $this->_('Duration'))
				->set('value', $cookie['duration'] ?? null)
		);
		$fieldset->add(
			/** @var InputfieldSelect */
			$this->modules->get('InputfieldSelect')
				->set('name', 'category_id')
				->set('label', $this->_('Category'))
				->set('required', true)
				->addOptions($cookie_category_options)
				->set('value', $cookie['category'] ?? array_key_first($cookie_category_options))
		);
		$fieldset->add(
			/** @var InputfieldTextarea */
			$this->modules->get('InputfieldTextarea')
				->set('name', 'description')
				->set('label', $this->_('Description'))
				->set('value', $cookie['description'] ?? null)
		);
		/** @var InputfieldSubmit */
		$submit = $this->modules->get('InputfieldSubmit');
		$submit->set('text', $this->_('Save cookie'));
		if ($cookie) {
			$submit->set('appendMarkup', '<a href="./" class="uk-margin-left">' . $this->_('Cancel') . '</a>');
		}
		$fieldset->add($submit);
		/** @var InputfieldForm */
		$form = $this->modules->get('InputfieldForm');
		$form->add($fieldset);

		return $table->render() . $form->render();
	}

	/**
	 * Process POST requests
	 */
	protected function processPostRequest() {
		if (!$this->session->CSRF->hasValidToken()) {
			throw new WireException($this->_('CSRF check failed, please try again'));
		}
		$cookie_table = $this->modules->get('CookieTable');
		$cookie = [
			'id' => $this->input->post('id', 'int') ?: null,
			'name' => $this->input->post('name', 'text'),
			'provider' => $this->input->post('provider', 'text'),
			'duration' => $this->input->post('duration', 'text'),
			'category_id' => $this->input->post('category_id', 'text'),
			'description' => $this->input->post('description', 'textarea'),
		];
		if ($this->input->post('delete', 'int') && $this->input->post('delete', 'int') === $cookie['id']) {
			$cookie = $cookie_table->getCookieByID($cookie['id']);
			if (!$cookie) {
				$this->session->error($this->_('Invalid cookie ID provided'));
				$this->session->redirect('./', false);
			}
			$cookie_table->deleteCookieByID($cookie['id']);
			$this->session->message(sprintf($this->_('Cookie deleted: %s'), $cookie['name']));
			$this->session->redirect('./', false);
		}
		if (empty($cookie['name']) || empty($cookie['category_id'])) {
			$this->session->error($this->_('Missing required parameters'));
			$this->session->redirect('./', false);
		}
		$cookie_id = $cookie_table->saveCookie(...$cookie);
		if ($cookie_id) {
			$cookie = $cookie_table->getCookieByID($cookie_id);
			if ($cookie) {
				$this->session->message(sprintf($this->_('Cookie saved: %s'), $cookie['name']));
				$this->session->redirect('./', false);
			}
		}
		$this->session->error($this->_('Unable to process your request, please try again'));
		$this->session->redirect('./', false);
	}
}
