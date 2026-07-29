(function () {
	'use strict';

	const settings = window.TBTMGAdmin || {};
	const strings = settings.strings || {};
	const pairsContainer = document.getElementById('tbtmg-pairs');
	const addPairButton = document.getElementById('tbtmg-add-pair');
	const generateButton = document.getElementById('tbtmg-generate');
	const spinner = document.getElementById('tbtmg-generator-spinner');
	const message = document.getElementById('tbtmg-generator-message');
	const validationMessage = document.getElementById('tbtmg-pair-validation');
	const standardPublish = document.getElementById('publish');
	const customPublish = document.getElementById('tbtmg-publish-game');
	const customDraft = document.getElementById('tbtmg-save-draft');

	function createPairRow(pair = { left: '', right: '' }) {
		const row = document.createElement('div');
		row.className = 'tbtmg-pair-row';
		row.dataset.tbtmgPair = '';

		const number = document.createElement('div');
		number.className = 'tbtmg-pair-number';
		number.dataset.tbtmgNumber = '';

		const leftField = createTextAreaField(strings.leftItem || 'Left item', 'left', pair.left || '');
		const rightField = createTextAreaField(strings.rightItem || 'Right item', 'right', pair.right || '');

		const actions = document.createElement('div');
		actions.className = 'tbtmg-pair-actions';
		actions.append(
			createActionButton('↑', 'up', strings.moveUp || 'Move up'),
			createActionButton('↓', 'down', strings.moveDown || 'Move down'),
			createDeleteButton()
		);

		row.append(number, leftField, rightField, actions);
		return row;
	}

	function createTextAreaField(labelText, side, value) {
		const wrapper = document.createElement('p');
		wrapper.className = 'tbtmg-field';
		const label = document.createElement('label');
		const labelSpan = document.createElement('span');
		labelSpan.textContent = labelText;
		const textarea = document.createElement('textarea');
		textarea.rows = 3;
		textarea.maxLength = 500;
		textarea.value = value;
		textarea.dataset[side === 'left' ? 'tbtmgLeft' : 'tbtmgRight'] = '';
		label.append(labelSpan, textarea);
		wrapper.append(label);
		return wrapper;
	}

	function createActionButton(text, action, label) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'button';
		button.textContent = text;
		button.setAttribute(`data-tbtmg-${action}`, '');
		button.setAttribute('aria-label', label);
		return button;
	}

	function createDeleteButton() {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'button-link-delete';
		button.dataset.tbtmgDelete = '';
		button.textContent = strings.delete || 'Delete';
		return button;
	}

	function pairRows() {
		return pairsContainer ? [...pairsContainer.querySelectorAll('[data-tbtmg-pair]')] : [];
	}

	function reindexPairs() {
		const rows = pairRows();
		rows.forEach((row, index) => {
			const number = row.querySelector('[data-tbtmg-number]');
			const left = row.querySelector('[data-tbtmg-left]');
			const right = row.querySelector('[data-tbtmg-right]');
			const up = row.querySelector('[data-tbtmg-up]');
			const down = row.querySelector('[data-tbtmg-down]');
			number.textContent = String(index + 1);
			left.name = `tbtmg_pairs[${index}][left]`;
			right.name = `tbtmg_pairs[${index}][right]`;
			up.disabled = index === 0;
			down.disabled = index === rows.length - 1;
		});
		validatePairs();
	}

	function validatePairs() {
		if (!pairsContainer) {
			return true;
		}

		const rows = pairRows();
		const inRange = rows.length >= Number(settings.minPairs || 4) && rows.length <= Number(settings.maxPairs || 12);
		const complete = rows.every((row) => {
			return row.querySelector('[data-tbtmg-left]').value.trim() && row.querySelector('[data-tbtmg-right]').value.trim();
		});
		const title = document.getElementById('title');
		const valid = inRange && complete && (!title || title.value.trim());

		if (validationMessage) {
			validationMessage.textContent = valid ? `${rows.length} complete pairs.` : (strings.pairRange || 'Complete all pairs before publishing.');
			validationMessage.classList.toggle('is-success', valid);
			validationMessage.classList.toggle('is-error', !valid);
		}

		if (standardPublish) {
			standardPublish.disabled = !valid;
			standardPublish.setAttribute('aria-disabled', String(!valid));
		}
		if (customPublish) {
			customPublish.disabled = !valid;
		}

		return valid;
	}

	function setMessage(text, type = '') {
		if (!message) {
			return;
		}
		message.textContent = text;
		message.classList.toggle('is-error', type === 'error');
		message.classList.toggle('is-success', type === 'success');
	}

	function hasEditableContent() {
		const title = document.getElementById('title');
		return Boolean((title && title.value.trim()) || pairRows().some((row) => {
			return row.querySelector('[data-tbtmg-left]').value.trim() || row.querySelector('[data-tbtmg-right]').value.trim();
		}));
	}

	async function generateGame() {
		const topicField = document.getElementById('tbtmg-generator-topic');
		const countField = document.getElementById('tbtmg-generator-count');
		const instructionsField = document.getElementById('tbtmg-generator-instructions');
		const topic = topicField ? topicField.value.trim() : '';

		if (!topic) {
			setMessage('Enter a topic before generating the game.', 'error');
			topicField?.focus();
			return;
		}

		if (hasEditableContent() && !window.confirm(strings.confirmRegenerate || 'Replace the current content?')) {
			return;
		}

		generateButton.disabled = true;
		spinner?.classList.add('is-active');
		setMessage(strings.generating || 'Generating your matching game…');

		try {
			const response = await fetch(settings.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': settings.nonce
				},
				body: JSON.stringify({
					topic,
					pair_count: Number(countField.value),
					additional_instructions: instructionsField ? instructionsField.value.trim() : ''
				})
			});

			const payload = await response.json().catch(() => ({}));
			if (!response.ok || !payload.success || !payload.game) {
				throw new Error(payload.message || strings.networkError || 'Generation failed.');
			}

			populateGame(payload.game);
			setMessage(strings.generated || 'The content is ready for review.', 'success');
		} catch (error) {
			setMessage(error.message || strings.networkError || 'Generation failed.', 'error');
		} finally {
			generateButton.disabled = false;
			spinner?.classList.remove('is-active');
		}
	}

	function setValue(id, value) {
		const element = document.getElementById(id);
		if (element) {
			element.value = value || '';
			element.dispatchEvent(new Event('input', { bubbles: true }));
		}
	}

	function populateGame(game) {
		setValue('title', game.title);
		setValue('tbtmg-topic', game.topic);
		setValue('tbtmg-eyebrow', game.eyebrow);
		setValue('tbtmg-instructions', game.instructions);
		setValue('tbtmg-left_column_title', game.left_column_title);
		setValue('tbtmg-right_column_title', game.right_column_title);
		setValue('tbtmg-completion_title', game.completion_title);
		setValue('tbtmg-completion_message', game.completion_message);

		if (pairsContainer) {
			pairsContainer.replaceChildren(...game.pairs.map((pair) => createPairRow(pair)));
			reindexPairs();
		}

		const generation = game.generation || {};
		setValue('tbtmg-generation-ai', generation.generated_by_ai ? '1' : '0');
		setValue('tbtmg-generation-model', generation.model);
		setValue('tbtmg-generation-prompt', generation.prompt_version || '1');
		setValue('tbtmg-generation-time', generation.generated_at);
		setValue('tbtmg-generation-request', generation.request_id);
	}

	async function copyText(text, button) {
		try {
			if (navigator.clipboard && window.isSecureContext) {
				await navigator.clipboard.writeText(text);
			} else {
				const temporary = document.createElement('textarea');
				temporary.value = text;
				temporary.style.position = 'fixed';
				temporary.style.opacity = '0';
				document.body.append(temporary);
				temporary.select();
				document.execCommand('copy');
				temporary.remove();
			}
			const previous = button.textContent;
			button.textContent = strings.copied || 'Copied.';
			window.setTimeout(() => {
				button.textContent = previous;
			}, 1200);
		} catch (error) {
			window.prompt('Copy this shortcode:', text);
		}
	}

	if (pairsContainer) {
		pairsContainer.addEventListener('click', (event) => {
			const row = event.target.closest('[data-tbtmg-pair]');
			if (!row) {
				return;
			}

			if (event.target.closest('[data-tbtmg-delete]')) {
				row.remove();
			} else if (event.target.closest('[data-tbtmg-up]') && row.previousElementSibling) {
				pairsContainer.insertBefore(row, row.previousElementSibling);
			} else if (event.target.closest('[data-tbtmg-down]') && row.nextElementSibling) {
				pairsContainer.insertBefore(row.nextElementSibling, row);
			}
			reindexPairs();
		});
		pairsContainer.addEventListener('input', validatePairs);
	}

	addPairButton?.addEventListener('click', () => {
		if (pairRows().length >= Number(settings.maxPairs || 12)) {
			validatePairs();
			return;
		}
		pairsContainer.append(createPairRow());
		reindexPairs();
		pairRows().at(-1)?.querySelector('[data-tbtmg-left]')?.focus();
	});

	generateButton?.addEventListener('click', generateGame);
	document.getElementById('title')?.addEventListener('input', validatePairs);
	customDraft?.addEventListener('click', () => document.getElementById('save-post')?.click());
	customPublish?.addEventListener('click', () => {
		if (validatePairs()) {
			standardPublish?.click();
		}
	});

	document.addEventListener('click', (event) => {
		const button = event.target.closest('[data-tbtmg-copy]');
		if (!button) {
			return;
		}
		const directValue = button.dataset.tbtmgCopyValue;
		const source = button.closest('.tbtmg-copy-row')?.querySelector('[data-tbtmg-copy-source]');
		copyText(directValue || source?.value || '', button);
	});

	if (pairsContainer) {
		reindexPairs();
	}
})();
