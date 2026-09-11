/**
 * TBT Teaching Tools — front-end generator and game library.
 *
 * Plain ES2018, no build step and no framework, matching the rest of the
 * plugin. Every write goes through the owner-scoped REST API; nothing here is
 * a security boundary.
 */
(function () {
	'use strict';

	var config = window.TBTMGTools || {};
	var strings = config.strings || {};

	function t(key) {
		return typeof strings[key] === 'string' ? strings[key] : '';
	}

	function sprintf(template, values) {
		var index = 0;
		return String(template).replace(/%(\d+\$)?[ds]/g, function (match, position) {
			var pick = position ? parseInt(position, 10) - 1 : index++;
			return typeof values[pick] === 'undefined' ? match : String(values[pick]);
		});
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (typeof text !== 'undefined' && text !== null) {
			node.textContent = String(text);
		}
		return node;
	}

	function request(url, options) {
		var settings = options || {};
		var init = {
			method: settings.method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			}
		};

		if (settings.body) {
			init.body = JSON.stringify(settings.body);
		}

		return window.fetch(url, init).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (payload) {
				if (response.ok) {
					return payload;
				}

				var error = new Error(payload && payload.message ? payload.message : t('networkError'));
				error.status = response.status;
				error.code = payload && payload.code ? payload.code : '';
				throw error;
			});
		});
	}

	function messageFor(error) {
		if (!error) {
			return t('networkError');
		}
		if (error.code === 'rest_cookie_invalid_nonce') {
			return t('sessionExpired');
		}
		if (error.status === 429) {
			return t('quota');
		}
		return error.message || t('networkError');
	}

	function notify(root, message, isError) {
		var box = root.querySelector('[data-tbtmg-notice]');
		if (!box) {
			return;
		}

		box.textContent = message || '';
		box.classList.toggle('tbtmg-notice--error', !!isError);
		box.hidden = !message;
	}

	function formatDate(iso) {
		if (!iso) {
			return '';
		}
		var date = new Date(iso);
		if (isNaN(date.getTime())) {
			return '';
		}
		return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
	}

	/**
	 * Add game_id to a page URL without disturbing what it already carries.
	 */
	function appendGameId(base, id) {
		var target = String(base);
		var hash = '';
		var index = target.indexOf('#');

		if (index !== -1) {
			hash = target.slice(index);
			target = target.slice(0, index);
		}

		return target + (target.indexOf('?') === -1 ? '?' : '&') + 'game_id=' + encodeURIComponent(id) + hash;
	}

	function copyToClipboard(value) {
		if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
			return window.navigator.clipboard.writeText(value);
		}

		return new Promise(function (resolve, reject) {
			var field = document.createElement('textarea');
			field.value = value;
			field.setAttribute('readonly', 'readonly');
			field.style.position = 'fixed';
			field.style.opacity = '0';
			document.body.appendChild(field);
			field.select();
			try {
				document.execCommand('copy');
				resolve();
			} catch (error) {
				reject(error);
			}
			document.body.removeChild(field);
		});
	}

	/**
	 * One read-only field plus a copy button.
	 */
	function copyRow(labelText, value) {
		var wrap = el('div', 'tbtmg-copy');
		var label = el('label', 'tbtmg-copy__label', labelText);
		var row = el('div', 'tbtmg-copy__row');
		var field = document.createElement('input');
		var button = el('button', 'tbtmg-button tbtmg-button--small', t('copy'));
		var id = 'tbtmg-copy-' + Math.random().toString(36).slice(2, 9);

		field.type = 'text';
		field.readOnly = true;
		field.value = value;
		field.id = id;
		label.setAttribute('for', id);
		button.type = 'button';

		button.addEventListener('click', function () {
			copyToClipboard(value).then(function () {
				button.textContent = t('copied');
				window.setTimeout(function () {
					button.textContent = t('copy');
				}, 1600);
			}).catch(function () {
				field.select();
			});
		});

		row.append(field, button);
		wrap.append(label, row);
		return wrap;
	}

	/**
	 * Share panel: game link and lesson shortcode. Built when the panel is
	 * expanded.
	 */
	function buildShare(game) {
		var panel = el('div', 'tbtmg-share__inner');

		if (!game || game.status !== 'publish') {
			panel.append(el('p', 'tbtmg-hint', t('draftNoShare')));
			return panel;
		}

		panel.append(copyRow(t('gameLink'), game.permalink));
		panel.append(copyRow(t('shortcodeLabel'), game.shortcode));

		return panel;
	}

	/**
	 * The create dialog: name the game, and it exists.
	 *
	 * Mounted on <body> rather than beside the button that opened it. A tool can
	 * sit inside a Divi section carrying its own transform, and a transformed
	 * ancestor makes position: fixed resolve against that element instead of the
	 * viewport — the overlay would then cover part of the page, not the page.
	 *
	 * @param {Object} options opener element and the generator URL to land on.
	 */
	function openCreateDialog(options) {
		var max = parseInt(config.titleMax, 10) || 30;
		var uid = 'tbtmg-create-' + Math.random().toString(36).slice(2, 9);
		var overlay = el('div', 'tbt tbt-tool tbtmg-modal');
		var dialog = el('div', 'tbtmg-modal__dialog');
		var heading = el('h2', 'tbtmg-modal__title', t('createHeading'));
		var field = el('div', 'tbtmg-field');
		var label = el('label', null, t('titleLabel'));
		var input = document.createElement('input');
		var counter = el('p', 'tbtmg-modal__count');
		var errorBox = el('p', 'tbtmg-notice tbtmg-notice--error tbtmg-modal__error');
		var actions = el('div', 'tbtmg-modal__actions');
		var cancel = el('button', 'tbtmg-button', t('cancel'));
		var create = el('button', 'tbtmg-button tbtmg-button--primary', t('create'));
		var pending = false;

		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-labelledby', uid + '-title');
		heading.id = uid + '-title';

		input.type = 'text';
		input.id = uid + '-input';
		input.maxLength = max;
		input.autocomplete = 'off';
		label.setAttribute('for', input.id);

		counter.id = uid + '-count';
		// The cap is a hero layout constraint, so the boundary is shown rather
		// than discovered by having the title silently truncated.
		counter.setAttribute('aria-live', 'polite');
		input.setAttribute('aria-describedby', counter.id);

		errorBox.hidden = true;
		cancel.type = 'button';
		create.type = 'button';

		function showError(message) {
			errorBox.textContent = message || '';
			errorBox.hidden = !message;
		}

		function sync() {
			counter.textContent = sprintf(t('charsLeft'), [Math.max(0, max - input.value.length)]);
			create.disabled = pending || input.value.trim() === '';
		}

		function focusable() {
			return Array.prototype.filter.call(dialog.querySelectorAll('input, button'), function (node) {
				return !node.disabled;
			});
		}

		function close() {
			// A create is already on its way; letting the dialog go now would
			// leave the teacher on the library with a draft appearing behind them.
			if (pending) {
				return;
			}

			overlay.remove();
			if (options.opener && typeof options.opener.focus === 'function') {
				options.opener.focus();
			}
		}

		function submit() {
			var title = input.value.trim();
			if (!title || pending) {
				return;
			}

			pending = true;
			create.disabled = true;
			cancel.disabled = true;
			create.textContent = t('creating');
			showError('');

			request(config.restBase, { method: 'POST', body: { title: title } }).then(function (response) {
				var game = response && response.game ? response.game : null;
				if (!game || !game.id) {
					throw new Error(t('createFailed'));
				}

				/*
				 * The response also carries the validation message explaining
				 * why a game with no pairs cannot publish yet. That is the
				 * expected outcome here, not a failure: the draft exists, and
				 * the generator is where the pairs get added.
				 */
				window.location.href = appendGameId(options.generatorUrl, game.id);
			}).catch(function (error) {
				pending = false;
				cancel.disabled = false;
				create.textContent = t('create');
				sync();
				showError(messageFor(error));
				input.focus();
			});
		}

		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				close();
			}
		});

		overlay.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				close();
				return;
			}

			if (event.key !== 'Tab') {
				return;
			}

			var nodes = focusable();
			if (!nodes.length) {
				event.preventDefault();
				return;
			}

			var first = nodes[0];
			var last = nodes[nodes.length - 1];

			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});

		input.addEventListener('input', sync);
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				submit();
			}
		});
		cancel.addEventListener('click', close);
		create.addEventListener('click', submit);

		field.append(label, input, counter);
		actions.append(cancel, create);
		dialog.append(heading, field, errorBox, actions);
		overlay.append(dialog);
		document.body.appendChild(overlay);

		sync();
		input.focus();
	}

	/**
	 * The generator tool.
	 */
	function initGenerator(root) {
		var gameId = parseInt(root.getAttribute('data-tbtmg-game-id'), 10) || 0;
		var pairsList = root.querySelector('[data-tbtmg-pairs]');
		var validation = root.querySelector('[data-tbtmg-pair-validation]');
		var pairCountBadge = root.querySelector('[data-tbtmg-pair-count]');
		var sharePanel = root.querySelector('[data-tbtmg-share]');
		var saveButton = root.querySelector('[data-tbtmg-save]');
		var newButton = root.querySelector('[data-tbtmg-new]');
		var saveStatus = root.querySelector('[data-tbtmg-save-status]');
		var generateButton = root.querySelector('[data-tbtmg-generate]');
		var generateStatus = root.querySelector('[data-tbtmg-generate-status]');
		var discardButton = root.querySelector('[data-tbtmg-discard]');
		var discardRow = root.querySelector('[data-tbtmg-discard-row]');
		var discardStatus = root.querySelector('[data-tbtmg-discard-status]');
		var libraryUrl = root.getAttribute('data-tbtmg-library-url') || '';
		var pairs = [];
		var generation = null;

		function fieldValue(name) {
			var node = root.querySelector('[data-tbtmg-field="' + name + '"]');
			return node ? node.value : '';
		}

		function setFieldValue(name, value) {
			var node = root.querySelector('[data-tbtmg-field="' + name + '"]');
			if (node) {
				node.value = typeof value === 'string' ? value : '';
			}
		}

		function generatorValue(name) {
			var node = root.querySelector('[data-tbtmg-generator="' + name + '"]');
			return node ? node.value : '';
		}

		/**
		 * The chosen band. Falls back to the hidden carrier a teacher without
		 * generation rights gets instead of the picker, so saving from the
		 * Wording stage preserves the level rather than clearing it.
		 */
		function currentLevel() {
			var chosen = root.querySelector('[data-tbtmg-levels] .tbtmg-level__input:checked');
			return chosen ? chosen.value : fieldValue('level');
		}

		function renderPairs() {
			pairsList.replaceChildren();

			pairs.forEach(function (pair, index) {
				var row = el('li', 'tbtmg-pair');
				var heading = el('span', 'tbtmg-pair__index', sprintf(t('pairLabel'), [index + 1]));
				var left = document.createElement('textarea');
				var right = document.createElement('textarea');
				var actions = el('div', 'tbtmg-pair__actions');
				var up = el('button', 'tbtmg-icon-button', '↑');
				var down = el('button', 'tbtmg-icon-button', '↓');
				var remove = el('button', 'tbtmg-icon-button tbtmg-icon-button--danger', '×');

				left.className = 'tbtmg-pair__side';
				right.className = 'tbtmg-pair__side';
				left.rows = 2;
				right.rows = 2;
				left.maxLength = 500;
				right.maxLength = 500;
				left.value = pair.left || '';
				right.value = pair.right || '';
				left.setAttribute('aria-label', t('left') + ' — ' + sprintf(t('pairLabel'), [index + 1]));
				right.setAttribute('aria-label', t('right') + ' — ' + sprintf(t('pairLabel'), [index + 1]));
				left.placeholder = t('left');
				right.placeholder = t('right');

				left.addEventListener('input', function () {
					pairs[index].left = left.value;
					updateValidation();
				});
				right.addEventListener('input', function () {
					pairs[index].right = right.value;
					updateValidation();
				});

				[up, down, remove].forEach(function (button) {
					button.type = 'button';
				});
				up.setAttribute('aria-label', t('moveUp'));
				down.setAttribute('aria-label', t('moveDown'));
				remove.setAttribute('aria-label', t('removePair'));
				up.disabled = index === 0;
				down.disabled = index === pairs.length - 1;

				up.addEventListener('click', function () {
					var previous = pairs[index - 1];
					pairs[index - 1] = pairs[index];
					pairs[index] = previous;
					renderPairs();
				});
				down.addEventListener('click', function () {
					var next = pairs[index + 1];
					pairs[index + 1] = pairs[index];
					pairs[index] = next;
					renderPairs();
				});
				remove.addEventListener('click', function () {
					pairs.splice(index, 1);
					renderPairs();
				});

				actions.append(up, down, remove);
				row.append(heading, left, right, actions);
				pairsList.append(row);
			});

			updateValidation();
		}

		function completePairs() {
			return pairs.filter(function (pair) {
				return String(pair.left || '').trim() !== '' && String(pair.right || '').trim() !== '';
			});
		}

		function updateValidation() {
			var complete = completePairs().length;
			var summary = sprintf(t('pairCount'), [complete, config.minPairs, config.maxPairs]);

			if (pairCountBadge) {
				pairCountBadge.textContent = summary;
			}

			if (!validation) {
				return;
			}

			var problem = '';
			if (complete < config.minPairs || complete > config.maxPairs) {
				problem = summary;
			} else if (complete !== pairs.length) {
				problem = t('pairsIncomplete');
			}

			validation.textContent = problem;
			validation.classList.toggle('is-invalid', problem !== '');
		}

		function setPairs(next) {
			pairs = (next || []).map(function (pair) {
				return { left: pair.left || '', right: pair.right || '' };
			});
			if (!pairs.length) {
				pairs = [];
				for (var index = 0; index < config.minPairs; index += 1) {
					pairs.push({ left: '', right: '' });
				}
			}
			renderPairs();
		}

		function showShare(game) {
			if (!sharePanel) {
				return;
			}
			sharePanel.replaceChildren(buildShare(game));
			sharePanel.hidden = false;
		}

		function payload() {
			return {
				title: fieldValue('title'),
				topic: fieldValue('topic'),
				level: currentLevel(),
				eyebrow: fieldValue('eyebrow'),
				instructions: fieldValue('instructions'),
				left_column_title: fieldValue('left_column_title'),
				right_column_title: fieldValue('right_column_title'),
				completion_title: fieldValue('completion_title'),
				completion_message: fieldValue('completion_message'),
				pairs: pairs.map(function (pair) {
					return { left: pair.left, right: pair.right };
				}),
				generation: generation || undefined
			};
		}

		function generate() {
			var topic = generatorValue('topic');
			if (!topic.trim()) {
				generateStatus.textContent = t('needTopic');
				return;
			}

			generateButton.disabled = true;
			generateStatus.textContent = t('generating');
			notify(root, '');

			request(config.generateUrl, {
				method: 'POST',
				body: {
					topic: topic,
					pair_count: parseInt(generatorValue('count'), 10) || config.minPairs,
					additional_instructions: generatorValue('instructions'),
					level: currentLevel()
				}
			}).then(function (response) {
				var game = response.game || {};
				generation = game.generation || null;

				if (!fieldValue('title').trim()) {
					setFieldValue('title', topic.split('\n')[0].slice(0, 200));
				}
				['topic', 'eyebrow', 'instructions', 'left_column_title', 'right_column_title', 'completion_title', 'completion_message'].forEach(function (key) {
					if (typeof game[key] === 'string' && game[key] !== '') {
						setFieldValue(key, game[key]);
					}
				});

				setPairs(game.pairs);
				openPanelContaining(pairsList);
				generateStatus.textContent = t('generated');
				// Regenerating replaces the content, so there is something to save again.
				offerSave();
			}).catch(function (error) {
				generateStatus.textContent = '';
				notify(root, messageFor(error), true);
			}).then(function () {
				generateButton.disabled = false;
			});
		}

		function save() {
			if (!fieldValue('title').trim()) {
				notify(root, t('needTitle'), true);
				return;
			}

			saveButton.disabled = true;
			saveStatus.textContent = t('saving');
			notify(root, '');

			var url = gameId ? config.restBase + '/' + gameId : config.restBase;

			request(url, { method: gameId ? 'PUT' : 'POST', body: payload() }).then(function (response) {
				var game = response.game || {};
				if (game.id) {
					gameId = game.id;
					root.setAttribute('data-tbtmg-game-id', String(game.id));
				}

				saveStatus.textContent = response.status === 'publish' ? t('savedPublished') : t('savedDraft');
				if (response.message) {
					notify(root, response.message, true);
				}
				showShare(game);
				syncDiscard(response.status);
				offerNewGame();
			}).catch(function (error) {
				saveStatus.textContent = '';
				notify(root, messageFor(error), true);
			}).then(function () {
				saveButton.disabled = false;
			});
		}

		/**
		 * After a save the obvious next move is a new game, not another save of
		 * the same one, so the button swaps. Editing anything afterwards brings
		 * Save back, otherwise a follow-up correction could not be saved.
		 */
		function offerNewGame() {
			if (!newButton || !saveButton) {
				return;
			}

			saveButton.hidden = true;
			newButton.hidden = false;
		}

		function offerSave() {
			if (!newButton || !saveButton || newButton.hidden) {
				return;
			}

			newButton.hidden = true;
			saveButton.hidden = false;
			saveStatus.textContent = '';
		}

		function startNewGame() {
			// A reload guarantees a clean slate — no stale pairs, generation
			// metadata or share panel from the game just saved. The saved game
			// is safe in the library.
			var url = new URL(window.location.href);
			url.searchParams.delete('game_id');
			window.location.href = url.toString();
		}

		/**
		 * Discard is for an abandoned draft. A save that publishes the game
		 * takes the button away without a reload: from that point the library's
		 * Delete is the only way to remove it, where a teacher can see it
		 * leaving the whole collection.
		 */
		function syncDiscard(status) {
			if (discardRow) {
				discardRow.hidden = 'draft' !== status;
			}
		}

		function discard() {
			if (!gameId || !libraryUrl) {
				return;
			}

			// Name the game being discarded: on a page with one title field and
			// three panels, "this game" is not specific enough to act on.
			var title = fieldValue('title').trim();
			if (!window.confirm(title ? sprintf(t('confirmDiscard'), [title]) : t('confirmDelete'))) {
				return;
			}

			discardButton.disabled = true;
			discardStatus.textContent = t('discarding');
			notify(root, '');

			request(config.restBase + '/' + gameId, { method: 'DELETE' }).then(function () {
				window.location.href = libraryUrl;
			}).catch(function (error) {
				discardStatus.textContent = '';
				discardButton.disabled = false;
				notify(root, messageFor(error), true);
			});
		}

		function openPanelContaining(node) {
			var panel = node ? node.closest('[data-tbtmg-panel]') : null;
			if (!panel || panel.classList.contains('is-open')) {
				return;
			}
			var toggle = panel.querySelector('.tbtmg-panel__toggle');
			if (toggle) {
				toggle.click();
			}
		}

		setPairs(initialPairs(root));

		if (generateButton) {
			generateButton.addEventListener('click', generate);
		}
		if (saveButton) {
			saveButton.addEventListener('click', save);
		}
		if (newButton) {
			newButton.addEventListener('click', startNewGame);
		}
		if (discardButton) {
			discardButton.addEventListener('click', discard);
		}
		root.addEventListener('input', offerSave);

		var addPair = root.querySelector('[data-tbtmg-add-pair]');
		if (addPair) {
			addPair.addEventListener('click', function () {
				if (pairs.length >= config.maxPairs) {
					updateValidation();
					return;
				}
				pairs.push({ left: '', right: '' });
				renderPairs();
				var fields = pairsList.querySelectorAll('.tbtmg-pair__side');
				if (fields.length) {
					fields[fields.length - 2].focus();
				}
			});
		}
	}

	/**
	 * Pairs handed over from the server for an existing game.
	 */
	function initialPairs(root) {
		var island = root.querySelector('[data-tbtmg-initial-pairs]');
		if (!island) {
			return [];
		}

		try {
			var parsed = JSON.parse(island.textContent);
			return Array.isArray(parsed) ? parsed : [];
		} catch (error) {
			return [];
		}
	}

	/**
	 * The library tool.
	 */
	function initLibrary(root) {
		var list = root.querySelector('[data-tbtmg-list]');
		var pagination = root.querySelector('[data-tbtmg-pagination]');
		var search = root.querySelector('[data-tbtmg-search]');
		var levelFilter = root.querySelector('[data-tbtmg-level-filter]');
		var createButton = root.querySelector('[data-tbtmg-create]');
		var libbar = root.querySelector('[data-tbtmg-libbar]');
		var libbarFilter = root.querySelector('[data-tbtmg-libbar-filter]');
		var libbarRule = root.querySelector('[data-tbtmg-libbar-rule]');
		var searchClear = root.querySelector('[data-tbtmg-search-clear]');
		var summary = root.querySelector('[data-tbtmg-summary]');
		var summaryText = root.querySelector('[data-tbtmg-summary-text]');
		/*
		 * The generator URL travels on the markup rather than in config: the
		 * bundle is localised once, before any shortcode has run, so a
		 * per-instance attribute cannot reach it. config.generatorUrl stays the
		 * fallback — it carries the filter and the current-page default, which
		 * is what a page with no attributes set has always used.
		 */
		var generatorUrl = root.getAttribute('data-tbtmg-generator-url') || config.generatorUrl || '';
		/*
		 * allTotal is the library's own size, which the summary counts against.
		 * The server seeds it so the first paint is right; every unfiltered load
		 * refreshes it afterwards.
		 */
		var state = {
			page: 1,
			search: '',
			level: '',
			totalPages: 1,
			allTotal: parseInt(libbar ? libbar.getAttribute('data-tbtmg-total') : '', 10) || 0
		};
		var searchTimer = null;

		function filtering() {
			return state.search !== '' || state.level !== '';
		}

		/*
		 * An empty library has nothing to search, so it shows its title, the
		 * rule and the Create button alone.
		 */
		function setEmpty(flag) {
			if (libbar) {
				libbar.classList.toggle('is-empty', flag);
			}
			if (libbarFilter) {
				libbarFilter.hidden = flag;
			}
			if (libbarRule) {
				libbarRule.hidden = !flag;
			}
		}

		function resetButton() {
			var button = el('button', 'tbtmg-libbar__link', t('clearFilters'));
			button.type = 'button';
			button.setAttribute('data-tbtmg-reset', '');
			return button;
		}

		function renderSummary(shown) {
			if (!summary || !summaryText) {
				return;
			}

			if (!filtering()) {
				summary.hidden = true;
				return;
			}

			summaryText.textContent = sprintf(t('filterOf'), [
				shown,
				state.allTotal,
				state.allTotal === 1 ? t('gameOne') : t('gameMany')
			]);
			summary.hidden = false;
		}

		function syncClear() {
			if (searchClear && search) {
				searchClear.hidden = search.value === '';
			}
		}

		// Used where the teacher has already made the choice explicit — the
		// clear button, Escape, Clear filters — so there is nothing to wait for.
		function runSearch() {
			window.clearTimeout(searchTimer);
			state.search = search ? search.value.trim() : '';
			state.page = 1;
			load();
		}

		function editUrl(game) {
			return appendGameId(generatorUrl || window.location.href, game.id);
		}

		function row(game) {
			var item = el('article', 'tbtmg-game-row');
			var main = el('div', 'tbtmg-game-row__main');
			var meta = el('p', 'tbtmg-game-row__meta');
			var actions = el('div', 'tbtmg-game-row__actions');
			var share = el('div', 'tbtmg-share');
			var badge = el(
				'span',
				'tbtmg-badge ' + (game.status === 'publish' ? 'tbtmg-badge--published' : 'tbtmg-badge--draft'),
				game.status === 'publish' ? t('published') : t('draft')
			);

			main.append(el('h3', 'tbtmg-game-row__title', game.title));
			meta.append(badge);

			// An unlevelled game says nothing rather than wearing an empty chip.
			if (game.level) {
				meta.append(el('span', 'tbtmg-badge tbtmg-badge--level', game.level));
			}

			meta.append(
				el('span', 'tbtmg-game-row__pairs', sprintf(t('pairCount'), [game.pair_count, config.minPairs, config.maxPairs])),
				el('span', 'tbtmg-game-row__date', sprintf(t('modified'), [formatDate(game.modified)]))
			);
			main.append(meta);

			/*
			 * Published games only. A draft has no working public URL —
			 * get_permalink() on a draft returns an address that 404s for a
			 * student and for the teacher alike — which is the same reason the
			 * share panel refuses to build one.
			 */
			var open = null;
			if (game.status === 'publish' && game.permalink) {
				open = el('a', 'tbtmg-button tbtmg-button--small', t('open'));
				open.href = game.permalink;
				open.target = '_blank';
				open.rel = 'noopener';
				open.setAttribute('aria-label', sprintf(t('openNewTab'), [game.title]));
			}

			var edit = el('a', 'tbtmg-button tbtmg-button--small', t('edit'));
			edit.href = editUrl(game);

			var shareButton = el('button', 'tbtmg-button tbtmg-button--small', t('share'));
			var duplicateButton = el('button', 'tbtmg-button tbtmg-button--small', t('duplicate'));
			var deleteButton = el('button', 'tbtmg-button tbtmg-button--small tbtmg-button--danger', t('delete'));

			[shareButton, duplicateButton, deleteButton].forEach(function (button) {
				button.type = 'button';
			});

			share.hidden = true;
			shareButton.setAttribute('aria-expanded', 'false');
			shareButton.addEventListener('click', function () {
				var opening = share.hidden;
				if (opening) {
					// Built lazily on first expand.
					share.replaceChildren(buildShare(game));
				}
				share.hidden = !opening;
				shareButton.setAttribute('aria-expanded', opening ? 'true' : 'false');
			});

			duplicateButton.addEventListener('click', function () {
				duplicateButton.disabled = true;
				request(config.restBase + '/' + game.id + '/duplicate', { method: 'POST' }).then(function () {
					notify(root, t('duplicated'));
					// The copy is in the library whether or not this filter shows it.
					state.allTotal += 1;
					load();
				}).catch(function (error) {
					notify(root, messageFor(error), true);
					duplicateButton.disabled = false;
				});
			});

			deleteButton.addEventListener('click', function () {
				if (!window.confirm(t('confirmDelete'))) {
					return;
				}
				deleteButton.disabled = true;
				request(config.restBase + '/' + game.id, { method: 'DELETE' }).then(function () {
					notify(root, t('deleted'));
					state.allTotal = Math.max(0, state.allTotal - 1);
					load();
				}).catch(function (error) {
					notify(root, messageFor(error), true);
					deleteButton.disabled = false;
				});
			});

			actions.append.apply(actions, [open, edit, shareButton, duplicateButton, deleteButton].filter(Boolean));
			item.append(main, actions, share);
			return item;
		}

		function renderPagination() {
			if (!pagination) {
				return;
			}

			pagination.replaceChildren();
			if (state.totalPages < 2) {
				pagination.hidden = true;
				return;
			}

			var previous = el('button', 'tbtmg-button tbtmg-button--small', t('prevPage'));
			var next = el('button', 'tbtmg-button tbtmg-button--small', t('nextPage'));
			previous.type = 'button';
			next.type = 'button';
			previous.disabled = state.page <= 1;
			next.disabled = state.page >= state.totalPages;

			previous.addEventListener('click', function () {
				state.page -= 1;
				load();
			});
			next.addEventListener('click', function () {
				state.page += 1;
				load();
			});

			pagination.append(previous, el('span', 'tbtmg-pagination__label', sprintf(t('pageOf'), [state.page, state.totalPages])), next);
			pagination.hidden = false;
		}

		function load() {
			list.setAttribute('aria-busy', 'true');
			list.replaceChildren(el('p', 'tbtmg-hint', t('loading')));

			var url = config.restBase + '?page=' + state.page + '&per_page=20&search=' + encodeURIComponent(state.search) +
				'&level=' + encodeURIComponent(state.level);

			request(url).then(function (response) {
				var items = response.items || [];
				var total = typeof response.total === 'number' ? response.total : items.length;
				state.totalPages = response.total_pages || 1;

				// A deletion can empty the last page; step back rather than
				// leaving the teacher staring at nothing.
				if (!items.length && state.page > 1) {
					state.page -= 1;
					load();
					return;
				}

				// An unfiltered load is the only honest measure of the library.
				if (!filtering()) {
					state.allTotal = total;
					setEmpty(state.allTotal === 0);
				}

				list.replaceChildren();
				if (!items.length) {
					var hint = el('p', 'tbtmg-hint', filtering() ? t('emptySearch') : t('empty'));
					if (filtering()) {
						hint.append(resetButton());
					}
					list.append(hint);
				} else {
					items.forEach(function (game) {
						list.append(row(game));
					});
				}

				renderSummary(total);
				renderPagination();
			}).catch(function (error) {
				list.replaceChildren();
				notify(root, messageFor(error), true);
			}).then(function () {
				list.setAttribute('aria-busy', 'false');
			});
		}

		if (search) {
			search.addEventListener('input', function () {
				// The button tracks the field; only the round trip is debounced.
				syncClear();
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () {
					state.search = search.value.trim();
					state.page = 1;
					load();
				}, 300);
			});

			search.addEventListener('keydown', function (event) {
				// With the field already empty, Escape belongs to whatever is
				// listening further up — a Divi overlay, the browser.
				if (event.key !== 'Escape' || search.value === '') {
					return;
				}

				event.preventDefault();
				search.value = '';
				syncClear();
				runSearch();
			});

			syncClear();
		}

		if (searchClear) {
			searchClear.addEventListener('click', function () {
				search.value = '';
				syncClear();
				runSearch();
				search.focus();
			});
		}

		/*
		 * Delegated: one of these buttons sits in the summary line, another is
		 * built into the no-matches hint on every load.
		 */
		root.addEventListener('click', function (event) {
			var target = event.target;
			if (!target || typeof target.closest !== 'function' || !target.closest('[data-tbtmg-reset]')) {
				return;
			}

			if (search) {
				search.value = '';
				syncClear();
			}
			if (levelFilter) {
				levelFilter.value = '';
				levelFilter.classList.remove('is-set');
			}

			window.clearTimeout(searchTimer);
			state.search = '';
			state.level = '';
			state.page = 1;
			load();

			if (search) {
				search.focus();
			}
		});

		/*
		 * The button is only rendered when the server resolved a generator URL,
		 * so there is always somewhere for a new game to land.
		 */
		if (createButton && generatorUrl) {
			createButton.addEventListener('click', function () {
				openCreateDialog({ opener: createButton, generatorUrl: generatorUrl });
			});
		}

		if (levelFilter) {
			// A select fires once per choice, so there is nothing to debounce.
			levelFilter.addEventListener('change', function () {
				state.level = levelFilter.value;
				levelFilter.classList.toggle('is-set', state.level !== '');
				state.page = 1;
				load();
			});
		}

		bindSearchShortcut();
		load();
	}

	var slashBound = false;

	/**
	 * "/" focuses the first visible library search on the page.
	 *
	 * Bound once per page rather than once per library: two libraries on one
	 * page would otherwise race to claim the same keystroke.
	 */
	function bindSearchShortcut() {
		if (slashBound) {
			return;
		}
		slashBound = true;

		document.addEventListener('keydown', function (event) {
			if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) {
				return;
			}

			// The create dialog owns the keyboard while it is open.
			if (document.querySelector('.tbtmg-modal')) {
				return;
			}

			var active = document.activeElement;
			if (active && (active.isContentEditable || /^(?:INPUT|TEXTAREA|SELECT)$/.test(active.tagName))) {
				return;
			}

			// An empty library hides its search; there is nothing to focus.
			var field = Array.prototype.filter.call(
				document.querySelectorAll('[data-tbtmg-search]'),
				function (node) {
					return node.offsetParent !== null;
				}
			)[0];

			if (!field) {
				return;
			}

			event.preventDefault();
			field.focus();
		});
	}

	/**
	 * Collapsible panels, shared by both tools.
	 */
	function initPanels(root) {
		root.querySelectorAll('.tbtmg-panel__toggle').forEach(function (toggle) {
			toggle.addEventListener('click', function () {
				var panel = toggle.closest('[data-tbtmg-panel]');
				var body = document.getElementById(toggle.getAttribute('aria-controls'));
				var open = toggle.getAttribute('aria-expanded') === 'true';

				toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
				if (panel) {
					panel.classList.toggle('is-open', !open);
				}
				if (body) {
					body.hidden = open;
				}
			});
		});
	}

	function initialise() {
		document.querySelectorAll('[data-tbtmg-tool]:not([data-tbtmg-ready])').forEach(function (root) {
			root.dataset.tbtmgReady = 'true';
			initPanels(root);

			if (root.getAttribute('data-tbtmg-tool') === 'generator') {
				initGenerator(root);
			} else {
				initLibrary(root);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialise);
	} else {
		initialise();
	}
})();
