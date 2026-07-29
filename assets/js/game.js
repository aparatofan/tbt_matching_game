(function () {
	'use strict';

	class TBTMatchingGame {
		constructor(container, config) {
			this.container = container;
			this.config = config;
			this.leftList = container.querySelector('[data-tbtmg-list="left"]');
			this.rightList = container.querySelector('[data-tbtmg-list="right"]');
			this.matchedCount = container.querySelector('[data-tbtmg-matched]');
			this.attemptCount = container.querySelector('[data-tbtmg-attempts]');
			this.completion = container.querySelector('[data-tbtmg-completion]');
			this.completionAttempts = container.querySelector('[data-tbtmg-completion-attempts]');
			this.liveRegion = container.querySelector('[data-tbtmg-live]');
			this.resetButton = container.querySelector('[data-tbtmg-reset]');
			this.matchedIds = new Set();
			this.attempts = 0;
			this.selectedCard = null;
			this.dragState = null;
			this.suppressClick = false;
			this.reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

			if (this.resetButton) {
				this.resetButton.addEventListener('click', () => this.reset());
			}

			this.reset();
		}

		shuffle(items) {
			const copy = [...items];
			for (let index = copy.length - 1; index > 0; index -= 1) {
				const randomIndex = Math.floor(Math.random() * (index + 1));
				[copy[index], copy[randomIndex]] = [copy[randomIndex], copy[index]];
			}
			return copy;
		}

		reset() {
			this.cleanupDrag();
			this.clearSelection();
			this.leftList.replaceChildren();
			this.rightList.replaceChildren();
			this.matchedIds = new Set();
			this.attempts = 0;
			this.completion.hidden = true;
			this.completion.classList.remove('is-visible');

			const leftPairs = this.config.settings.shuffle_on_load ? this.shuffle(this.config.pairs) : [...this.config.pairs];
			const rightPairs = this.config.settings.shuffle_on_load ? this.shuffle(this.config.pairs) : [...this.config.pairs];

			leftPairs.forEach((pair) => this.leftList.append(this.createCard(pair, 'left')));
			rightPairs.forEach((pair) => this.rightList.append(this.createCard(pair, 'right')));
			this.updateStatus();
		}

		createCard(pair, side) {
			const card = document.createElement('button');
			const text = side === 'left' ? pair.left : pair.right;
			card.type = 'button';
			card.className = 'tbtmg-card';
			card.dataset.pairId = String(pair.id);
			card.dataset.side = side;
			card.setAttribute('aria-pressed', 'false');
			card.setAttribute('aria-label', `${side === 'left' ? this.config.labels.leftCard : this.config.labels.rightCard}: ${text}`);
			card.textContent = text;

			if (this.config.settings.allow_click) {
				card.addEventListener('click', (event) => this.handleCardClick(event));
				card.addEventListener('keydown', (event) => this.handleCardKeydown(event));
			}

			if (this.config.settings.allow_drag && window.PointerEvent) {
				card.addEventListener('pointerdown', (event) => this.startPointerDrag(event));
			}

			return card;
		}

		handleCardClick(event) {
			if (this.suppressClick) {
				event.preventDefault();
				return;
			}

			const card = event.currentTarget;
			if (card.classList.contains('is-matched')) {
				return;
			}

			if (!this.selectedCard) {
				this.selectCard(card);
				this.announce(this.config.labels.firstSelected);
				return;
			}

			if (this.selectedCard === card) {
				this.clearSelection();
				return;
			}

			if (this.selectedCard.dataset.side === card.dataset.side) {
				this.clearSelection();
				this.selectCard(card);
				this.announce(this.config.labels.selectionChanged);
				return;
			}

			const firstCard = this.selectedCard;
			this.clearSelection();
			this.attemptMatch(firstCard, card);
		}

		handleCardKeydown(event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				this.clearSelection();
			}
		}

		selectCard(card) {
			this.selectedCard = card;
			card.classList.add('is-selected');
			card.setAttribute('aria-pressed', 'true');
		}

		clearSelection() {
			if (this.selectedCard) {
				this.selectedCard.classList.remove('is-selected');
				this.selectedCard.setAttribute('aria-pressed', 'false');
			}
			this.selectedCard = null;
		}

		attemptMatch(cardA, cardB) {
			if (!cardA || !cardB || cardA.dataset.side === cardB.dataset.side) {
				return;
			}

			this.attempts += 1;
			if (cardA.dataset.pairId === cardB.dataset.pairId) {
				this.markMatched(cardA, cardB);
			} else {
				this.rejectMatch(cardA, cardB);
			}
			this.updateStatus();
		}

		markMatched(cardA, cardB) {
			this.matchedIds.add(cardA.dataset.pairId);
			[cardA, cardB].forEach((card) => {
				card.classList.remove('is-selected', 'is-drop-target', 'is-drag-source');
				card.classList.add('is-matched', 'is-match-pop');
				card.setAttribute('aria-pressed', 'true');
				card.disabled = true;
				window.setTimeout(() => card.classList.remove('is-match-pop'), 450);
			});
			this.announce(this.config.labels.correct);
		}

		rejectMatch(cardA, cardB) {
			[cardA, cardB].forEach((card) => {
				card.classList.remove('is-drop-target');
				card.classList.add('is-jittering');
				window.setTimeout(() => card.classList.remove('is-jittering'), 420);
			});
			this.announce(this.config.labels.incorrect);
		}

		updateStatus() {
			this.matchedCount.textContent = String(this.matchedIds.size);
			if (this.attemptCount) {
				this.attemptCount.textContent = String(this.attempts);
			}

			if (this.matchedIds.size === this.config.pairs.length) {
				const completeText = this.config.labels.complete.replace('%d', String(this.attempts));
				this.completionAttempts.textContent = completeText;
				this.completion.hidden = false;
				this.completion.classList.add('is-visible');
				this.announce(completeText);
				this.completion.scrollIntoView({
					behavior: this.reducedMotion ? 'auto' : 'smooth',
					block: 'nearest'
				});
			}
		}

		announce(message) {
			if (!this.liveRegion) {
				return;
			}
			this.liveRegion.textContent = '';
			window.setTimeout(() => {
				this.liveRegion.textContent = message;
			}, 20);
		}

		startPointerDrag(event) {
			const card = event.currentTarget;
			if (card.disabled || (event.pointerType === 'mouse' && event.button !== 0)) {
				return;
			}

			const rect = card.getBoundingClientRect();
			const ghost = document.createElement('div');
			ghost.className = 'tbtmg-drag-ghost';
			ghost.textContent = card.textContent;
			document.body.append(ghost);

			this.dragState = {
				pointerId: event.pointerId,
				source: card,
				ghost,
				offsetX: event.clientX - rect.left,
				offsetY: event.clientY - rect.top,
				startX: event.clientX,
				startY: event.clientY,
				didMove: false,
				selectionCleared: false,
				target: null
			};

			card.setPointerCapture(event.pointerId);
			card.classList.add('is-drag-source');
			this.positionGhost(event.clientX, event.clientY);

			card.addEventListener('pointermove', this.boundPointerMove = (moveEvent) => this.onPointerMove(moveEvent));
			card.addEventListener('pointerup', this.boundPointerUp = (upEvent) => this.endPointerDrag(upEvent));
			card.addEventListener('pointercancel', this.boundPointerCancel = () => this.cleanupDrag());
		}

		onPointerMove(event) {
			if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
				return;
			}

			const distance = Math.hypot(event.clientX - this.dragState.startX, event.clientY - this.dragState.startY);
			if (distance > 5) {
				this.dragState.didMove = true;
				if (!this.dragState.selectionCleared) {
					this.clearSelection();
					this.dragState.selectionCleared = true;
				}
			}

			this.positionGhost(event.clientX, event.clientY);
			this.updateDropTarget(event.clientX, event.clientY);
		}

		positionGhost(clientX, clientY) {
			if (!this.dragState) {
				return;
			}
			const { ghost, offsetX, offsetY } = this.dragState;
			const left = clientX - Math.min(offsetX, ghost.offsetWidth - 20);
			const top = clientY - Math.min(offsetY, ghost.offsetHeight - 20);
			ghost.style.left = `${left}px`;
			ghost.style.top = `${top}px`;
		}

		updateDropTarget(clientX, clientY) {
			if (!this.dragState) {
				return;
			}

			this.dragState.ghost.hidden = true;
			const element = document.elementFromPoint(clientX, clientY);
			this.dragState.ghost.hidden = false;
			const card = element ? element.closest('.tbtmg-card') : null;
			const valid = card &&
				card.closest('.tbtmg-game') === this.container &&
				card !== this.dragState.source &&
				!card.disabled &&
				card.dataset.side !== this.dragState.source.dataset.side;

			if (this.dragState.target && this.dragState.target !== card) {
				this.dragState.target.classList.remove('is-drop-target');
			}

			this.dragState.target = valid ? card : null;
			if (this.dragState.target) {
				this.dragState.target.classList.add('is-drop-target');
			}
		}

		endPointerDrag(event) {
			if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
				return;
			}

			const { source, target, didMove } = this.dragState;
			this.cleanupDrag();
			if (didMove) {
				this.suppressClick = true;
				window.setTimeout(() => {
					this.suppressClick = false;
				}, 0);
				if (target) {
					this.attemptMatch(source, target);
				}
			}
		}

		cleanupDrag() {
			if (!this.dragState) {
				return;
			}

			const { source, ghost, target } = this.dragState;
			source.classList.remove('is-drag-source');
			if (target) {
				target.classList.remove('is-drop-target');
			}
			source.removeEventListener('pointermove', this.boundPointerMove);
			source.removeEventListener('pointerup', this.boundPointerUp);
			source.removeEventListener('pointercancel', this.boundPointerCancel);
			ghost.remove();
			this.dragState = null;
		}
	}

	function initialiseGames() {
		document.querySelectorAll('.tbtmg-game:not([data-tbtmg-initialised])').forEach((container) => {
			const dataElement = container.querySelector('.tbtmg-game-data');
			if (!dataElement) {
				return;
			}

			try {
				const config = JSON.parse(dataElement.textContent);
				container.dataset.tbtmgInitialised = 'true';
				new TBTMatchingGame(container, config);
			} catch (error) {
				container.dataset.tbtmgInitialised = 'error';
				if (window.console) {
					console.error('TBT Matching Games could not initialise.', error);
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialiseGames);
	} else {
		initialiseGames();
	}
})();
