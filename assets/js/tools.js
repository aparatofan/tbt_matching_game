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
	 * Share panel: link, QR and shortcode. The QR renders as the panel is
	 * built, never behind a second click — students scan it off a projected
	 * screen mid-lesson.
	 */
	function buildShare(game) {
		var panel = el('div', 'tbtmg-share__inner');

		if (!game || game.status !== 'publish') {
			panel.append(el('p', 'tbtmg-hint', t('draftNoShare')));
			return panel;
		}

		panel.append(copyRow(t('gameLink'), game.permalink));

		var qrWrap = el('div', 'tbtmg-qr');
		var qrTarget = el('div', 'tbtmg-qr__code');
		qrWrap.append(qrTarget, el('p', 'tbtmg-qr__caption', t('scanToPlay')));
		panel.append(qrWrap);

		if (typeof window.QRCode !== 'undefined') {
			new window.QRCode(qrTarget, {
				text: game.permalink,
				width: 190,
				height: 190,
				correctLevel: window.QRCode.CorrectLevel.M
			});
		}

		panel.append(copyRow(t('shortcodeLabel'), game.shortcode));

		return panel;
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
					additional_instructions: generatorValue('instructions')
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
		var state = { page: 1, search: '', totalPages: 1 };
		var searchTimer = null;

		function editUrl(game) {
			var base = config.generatorUrl || window.location.href;
			return base + (base.indexOf('?') === -1 ? '?' : '&') + 'game_id=' + game.id;
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
			meta.append(
				badge,
				el('span', 'tbtmg-game-row__pairs', sprintf(t('pairCount'), [game.pair_count, config.minPairs, config.maxPairs])),
				el('span', 'tbtmg-game-row__date', sprintf(t('modified'), [formatDate(game.modified)]))
			);
			main.append(meta);

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
					// Built on expand so the QR is on screen the moment the
					// panel is, with no second click.
					share.replaceChildren(buildShare(game));
				}
				share.hidden = !opening;
				shareButton.setAttribute('aria-expanded', opening ? 'true' : 'false');
			});

			duplicateButton.addEventListener('click', function () {
				duplicateButton.disabled = true;
				request(config.restBase + '/' + game.id + '/duplicate', { method: 'POST' }).then(function () {
					notify(root, t('duplicated'));
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
					load();
				}).catch(function (error) {
					notify(root, messageFor(error), true);
					deleteButton.disabled = false;
				});
			});

			actions.append(edit, shareButton, duplicateButton, deleteButton);
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

			var url = config.restBase + '?page=' + state.page + '&per_page=20&search=' + encodeURIComponent(state.search);

			request(url).then(function (response) {
				var items = response.items || [];
				state.totalPages = response.total_pages || 1;

				// A deletion can empty the last page; step back rather than
				// leaving the teacher staring at nothing.
				if (!items.length && state.page > 1) {
					state.page -= 1;
					load();
					return;
				}

				list.replaceChildren();
				if (!items.length) {
					list.append(el('p', 'tbtmg-hint', state.search ? t('emptySearch') : t('empty')));
				} else {
					items.forEach(function (game) {
						list.append(row(game));
					});
				}

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
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () {
					state.search = search.value.trim();
					state.page = 1;
					load();
				}, 300);
			});
		}

		load();
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
