(function () {
	'use strict';

	let instanceCounter = 0;

	/* ---- Reporting to the teacher's live panel ----

	   Optional in every direction. TBT Notes owns the activity routes, and the
	   game must keep working when Notes is not there, so the whole surface is
	   gated on a base URL the server only supplies when Notes is active.

	   Module level, not instance level, on purpose. A lesson page can carry
	   several games, and a heartbeat per game would be several identical
	   requests every twenty seconds saying the same thing about the same
	   student. One page, one pulse. */

	const PRESENCE_EVERY = 20000;
	let presenceTimer = null;

	function activityConfig() {
		return (typeof window.TBTMGGame === 'object' && window.TBTMGGame) || {};
	}

	function postActivity(path, body) {
		const cfg = activityConfig();
		if (!cfg.activityBase || !cfg.activityNonce) {
			return;
		}

		// Reporting is a side effect of playing, never a gate on it: a failed
		// request is swallowed rather than shown. A student mid-lesson cannot act
		// on "could not reach the progress panel".
		fetch(cfg.activityBase + path, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.activityNonce
			},
			body: JSON.stringify(body || {}),
			// The completion fires as the result overlay opens, and a learner who
			// closes the tab on it should still be recorded.
			keepalive: true
		}).catch(() => {});
	}

	/* The heartbeat says "still working" and writes no history — the server keeps
	   it in a short-lived transient. It starts on the first interaction rather
	   than on page load: a lesson page that merely contains a game must not mark
	   every student in the room as working the moment the page paints. */
	function startPresence() {
		if (presenceTimer || !activityConfig().activityBase) {
			return;
		}
		postActivity('/presence', {});
		presenceTimer = window.setInterval(() => postActivity('/presence', {}), PRESENCE_EVERY);
	}

	function stopPresence() {
		if (presenceTimer) {
			window.clearInterval(presenceTimer);
			presenceTimer = null;
		}
	}

	class TBTMatchingGame {
		constructor(container, config) {
			this.container = container;
			this.config = config;
			this.leftList = container.querySelector('[data-tbtmg-list="left"]');
			this.rightList = container.querySelector('[data-tbtmg-list="right"]');
			this.board = container.querySelector('.tbtmg-board');
			this.connectionLayer = container.querySelector('[data-tbtmg-connections]');
			this.matchedCount = container.querySelector('[data-tbtmg-matched]');
			this.attemptCount = container.querySelector('[data-tbtmg-attempts]');
			this.result = container.querySelector('[data-tbtmg-result]');
			this.resultAttempts = container.querySelector('[data-tbtmg-result-attempts]');
			this.resultTimers = [];
			this.resultSequence = 0;
			this.liveRegion = container.querySelector('[data-tbtmg-live]');
			this.resetButton = container.querySelector('[data-tbtmg-reset]');
			this.matchedIds = new Set();
			this.attempts = 0;
			/* Reporting state. completionSent is a once-per-page-load guard: Shuffle
			   & restart can reach showResult() again, and a second row would tell the
			   teacher a student finished twice. startedAt is set on the first
			   interaction, not here, so a game sitting unopened on a lesson page is
			   not timed. Neither is cleared by reset(): surviving a restart is the
			   point. */
			this.completionSent = false;
			this.startedAt = 0;
			this.selectedCard = null;
			this.dragState = null;
			this.suppressClick = false;
			this.connectionFrame = null;
			this.resizeObserver = null;
			this.animatedConnectionIds = new Set();
			this.reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			instanceCounter += 1;
			this.instanceId = `tbtmg-${instanceCounter}`;

			window.addEventListener('blur', () => this.cancelDrag());
			document.addEventListener('visibilitychange', () => {
				if (document.hidden) {
					this.cancelDrag();
				}
			});
			document.addEventListener('contextmenu', () => this.cancelDrag());
			document.addEventListener('keydown', (event) => {
				if (event.key === 'Escape') {
					this.cancelDrag();
				}
				if (
					(event.key === 'Escape' || event.key === 'Enter') &&
					this.result.classList.contains('is-visible')
				) {
					this.dismissResult(this.resultSequence);
				}
			});

			this.result.addEventListener('click', () => {
				if (this.result.classList.contains('is-visible')) {
					this.dismissResult(this.resultSequence);
				}
			});

			if (this.resetButton) {
				this.resetButton.addEventListener('click', () => this.reset());
			}

			this.setupConnections();
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

		setupConnections() {
			if (!this.board || !this.connectionLayer) {
				return;
			}

			window.addEventListener('resize', () => this.scheduleConnectionDraw(), { passive: true });

			if (window.ResizeObserver) {
				this.resizeObserver = new ResizeObserver(() => this.scheduleConnectionDraw());
				this.resizeObserver.observe(this.board);
			}

			if (document.fonts && document.fonts.ready) {
				document.fonts.ready.then(() => this.scheduleConnectionDraw());
			}
		}

		clearConnections() {
			if (this.connectionFrame) {
				window.cancelAnimationFrame(this.connectionFrame);
				this.connectionFrame = null;
			}
			if (this.connectionLayer) {
				this.connectionLayer.replaceChildren();
			}
		}

		scheduleConnectionDraw() {
			if (!this.connectionLayer || this.connectionFrame) {
				return;
			}
			this.connectionFrame = window.requestAnimationFrame(() => {
				this.connectionFrame = null;
				this.drawConnections();
			});
		}

		findCard(list, pairId) {
			return Array.from(list.children).find((card) => card.dataset.pairId === pairId) || null;
		}

		getConnectionVariation(pairId) {
			const hash = Array.from(pairId).reduce((total, character) => {
				return ((total * 31) + character.charCodeAt(0)) % 997;
			}, 0);
			return ((hash % 7) - 3) * 4;
		}

		drawConnections() {
			if (!this.board || !this.connectionLayer) {
				return;
			}

			const stackedLayout = window.matchMedia && window.matchMedia('(max-width: 760px)').matches;
			if (stackedLayout || this.matchedIds.size === 0) {
				this.connectionLayer.replaceChildren();
				return;
			}

			const boardRect = this.board.getBoundingClientRect();
			if (!boardRect.width || !boardRect.height) {
				return;
			}

			this.connectionLayer.setAttribute('viewBox', `0 0 ${boardRect.width} ${boardRect.height}`);
			const paths = document.createDocumentFragment();

			this.matchedIds.forEach((pairId) => {
				const leftCard = this.findCard(this.leftList, pairId);
				const rightCard = this.findCard(this.rightList, pairId);
				if (!leftCard || !rightCard) {
					return;
				}

				const leftRect = leftCard.getBoundingClientRect();
				const rightRect = rightCard.getBoundingClientRect();
				const startX = leftRect.right - boardRect.left;
				const startY = leftRect.top + (leftRect.height / 2) - boardRect.top;
				const endX = rightRect.left - boardRect.left;
				const endY = rightRect.top + (rightRect.height / 2) - boardRect.top;
				const gap = Math.max(1, endX - startX);
				const curve = Math.max(28, Math.min(88, gap * 0.52));
				const variation = this.getConnectionVariation(pairId);
				const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

				path.classList.add('tbtmg-connection');
				path.dataset.pairId = pairId;
				path.setAttribute('pathLength', '1');
				path.setAttribute(
					'd',
					`M ${startX} ${startY} C ${startX + curve} ${startY + variation}, ${endX - curve} ${endY - variation}, ${endX} ${endY}`
				);

				if (!this.animatedConnectionIds.has(pairId)) {
					path.classList.add('is-drawing');
					this.animatedConnectionIds.add(pairId);
				}

				paths.append(path);
			});

			this.connectionLayer.replaceChildren(paths);
		}

		reset() {
			this.cleanupDrag();
			this.sweepOrphans();
			this.clearSelection();
			this.clearConnections();
			this.animatedConnectionIds.clear();
			this.leftList.replaceChildren();
			this.rightList.replaceChildren();
			this.matchedIds = new Set();
			this.attempts = 0;
			this.clearResultTimers();
			this.resultSequence += 1;
			this.result.classList.remove('is-visible', 'is-counting');
			this.result.hidden = true;

			const leftPairs = this.config.settings.shuffle_on_load ? this.shuffle(this.config.pairs) : [...this.config.pairs];
			const rightPairs = this.config.settings.shuffle_on_load ? this.shuffle(this.config.pairs) : [...this.config.pairs];

			leftPairs.forEach((pair) => this.leftList.append(this.createCard(pair, 'left')));
			rightPairs.forEach((pair) => this.rightList.append(this.createCard(pair, 'right')));
			this.scheduleConnectionDraw();
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

		/* Called from the first card interaction of the sitting, and from every one
		   after it. Starts the clock and the heartbeat; both are no-ops on every
		   later call.

		   A game that has already reported never beats again. After showResult() the
		   board dismisses itself and sorts A–Z for review, so the finished board
		   invites exactly the touches that would otherwise re-arm the heartbeat and
		   leave the teacher's panel showing "Working" on finished work for the rest
		   of the day. Shuffle & restart is not an exception: completionSent survives
		   it by design, and a genuinely fresh sitting is a fresh page load. */
		noteInteraction() {
			if (this.completionSent) {
				return;
			}
			if (!this.startedAt) {
				this.startedAt = Date.now();
			}
			startPresence();
		}

		reportCompletion() {
			if (this.completionSent) {
				return;
			}
			this.completionSent = true;
			stopPresence();

			const activity = this.config.activity;
			if (!activity || !activity.objectRef) {
				return;
			}

			// No score. Every pair matched is the only way to reach this point, so
			// n of n carries no information the event itself does not already give.
			// Attempts is not a score and is not sent.
			postActivity('', {
				tool: 'matching',
				object_ref: activity.objectRef,
				object_title: activity.objectTitle,
				post_id: activity.postId || 0,
				duration_seconds: this.startedAt
					? Math.max(0, Math.round((Date.now() - this.startedAt) / 1000))
					: null
			});
		}

		handleCardClick(event) {
			this.noteInteraction();

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
				window.setTimeout(() => {
					card.classList.remove('is-match-pop');
					this.scheduleConnectionDraw();
				}, 450);
			});
			this.scheduleConnectionDraw();
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
				this.showResult();
			}
		}

		clearResultTimers() {
			this.resultTimers.forEach((timerId) => window.clearTimeout(timerId));
			this.resultTimers = [];
		}

		laterResult(fn, ms) {
			this.resultTimers.push(window.setTimeout(fn, ms));
		}

		showResult() {
			this.reportCompletion();
			this.resultSequence += 1;
			const seq = this.resultSequence;
			const completeText = this.config.labels.complete.replace('%d', String(this.attempts));

			this.resultAttempts.textContent = completeText;
			this.result.hidden = false;
			// The reflow is required: adding both in the same frame skips the fade-in.
			void this.result.offsetWidth;
			this.result.classList.add('is-visible');
			this.laterResult(() => this.result.classList.add('is-counting'), 60);
			this.announce(completeText);
			this.laterResult(() => this.dismissResult(seq), 5060);
		}

		dismissResult(seq) {
			if (seq !== this.resultSequence) {
				return;
			}

			this.clearResultTimers();
			this.result.classList.remove('is-visible', 'is-counting');
			this.laterResult(() => {
				this.result.hidden = true;
			}, 320);
			this.laterResult(() => this.sortForReview(seq), 560);
		}

		/**
		 * Move the cards into review order.
		 *
		 * Every lookup happens before the first move, so a board missing a card
		 * leaves the order untouched instead of half sorted. 'append' on an
		 * existing child moves it, which keeps the listeners bound in createCard().
		 *
		 * @param {Array} order Pairs in the order the rows should end up in.
		 * @return {boolean} False when a card could not be found.
		 */
		applyReviewOrder(order) {
			const moves = [];

			for (const pair of order) {
				const pairId = String(pair.id);
				const leftCard = this.findCard(this.leftList, pairId);
				const rightCard = this.findCard(this.rightList, pairId);
				if (!leftCard || !rightCard) {
					return false;
				}
				moves.push([leftCard, rightCard]);
			}

			moves.forEach(([leftCard, rightCard]) => {
				this.leftList.append(leftCard);
				this.rightList.append(rightCard);
			});

			return true;
		}

		sortForReview(seq) {
			if (seq !== this.resultSequence) {
				return;
			}

			// No explicit locale: the visitor's collation sorts the diacritics.
			const order = [...this.config.pairs].sort((a, b) => a.left.localeCompare(b.left, undefined, { sensitivity: 'base' }));

			if (this.reducedMotion) {
				this.applyReviewOrder(order);
				this.scheduleConnectionDraw();
				return;
			}

			// The lines are positioned against the board and would stay pinned to
			// the old card positions while the cards slide out from under them.
			// animatedConnectionIds is deliberately left populated, so the lines
			// do not replay their draw animation when they come back.
			this.clearConnections();

			const cards = [...this.leftList.children, ...this.rightList.children];
			const firstRects = new Map();
			cards.forEach((card) => firstRects.set(card, card.getBoundingClientRect()));

			if (!this.applyReviewOrder(order)) {
				this.scheduleConnectionDraw();
				return;
			}

			cards.forEach((card) => {
				const firstRect = firstRects.get(card);
				const lastRect = card.getBoundingClientRect();
				card.style.willChange = 'transform';
				card.style.transition = 'none';
				card.style.transform = `translate(${firstRect.left - lastRect.left}px, ${firstRect.top - lastRect.top}px)`;
			});

			// Without this the browser coalesces both transform writes and nothing animates.
			void this.board.offsetWidth;

			[this.leftList, this.rightList].forEach((list) => {
				Array.from(list.children).forEach((card, rowIndex) => {
					card.style.transition = `transform 620ms cubic-bezier(0.22, 0.61, 0.36, 1) ${rowIndex * 30}ms`;
					card.style.transform = '';
				});
			});

			this.laterResult(() => {
				cards.forEach((card) => {
					card.style.transition = '';
					card.style.transform = '';
					card.style.willChange = '';
				});
				this.scheduleConnectionDraw();
			}, 620 + (order.length * 30) + 60);
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
			this.noteInteraction();

			if (this.dragState) {
				return;
			}

			this.sweepOrphans();

			const card = event.currentTarget;
			if (card.disabled || (event.pointerType === 'mouse' && event.button !== 0)) {
				return;
			}

			const rect = card.getBoundingClientRect();
			const state = {
				pointerId: event.pointerId,
				source: card,
				ghost: null,
				offsetX: event.clientX - rect.left,
				offsetY: event.clientY - rect.top,
				startX: event.clientX,
				startY: event.clientY,
				didMove: false,
				selectionCleared: false,
				target: null,
				onMove: null,
				onUp: null,
				onCancel: null,
				onLostCapture: null
			};

			state.onMove = (moveEvent) => this.onPointerMove(moveEvent);
			state.onUp = (upEvent) => this.endPointerDrag(upEvent);
			state.onCancel = (cancelEvent) => {
				if (this.dragState && cancelEvent.pointerId === this.dragState.pointerId) {
					this.cancelDrag();
				}
			};
			state.onLostCapture = () => this.onLostPointerCapture();

			this.dragState = state;

			window.addEventListener('pointermove', state.onMove);
			window.addEventListener('pointerup', state.onUp);
			window.addEventListener('pointercancel', state.onCancel);
			card.addEventListener('lostpointercapture', state.onLostCapture);

			try {
				card.setPointerCapture(event.pointerId);
			} catch (error) {
				// Best effort: the window listeners keep the drag working without capture.
			}
		}

		onPointerMove(event) {
			if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
				return;
			}

			const distance = Math.hypot(event.clientX - this.dragState.startX, event.clientY - this.dragState.startY);
			if (distance > 5 && !this.dragState.didMove) {
				this.dragState.didMove = true;
				this.beginVisualDrag();
			}

			if (!this.dragState.didMove) {
				return;
			}

			this.positionGhost(event.clientX, event.clientY);
			this.updateDropTarget(event.clientX, event.clientY);
		}

		beginVisualDrag() {
			if (!this.dragState) {
				return;
			}

			const ghost = document.createElement('div');
			ghost.className = 'tbtmg-drag-ghost';
			ghost.textContent = this.dragState.source.textContent;
			ghost.dataset.tbtmgOwner = this.instanceId;
			document.body.append(ghost);
			this.dragState.ghost = ghost;
			this.dragState.source.classList.add('is-drag-source');

			if (!this.dragState.selectionCleared) {
				this.clearSelection();
				this.dragState.selectionCleared = true;
			}
		}

		positionGhost(clientX, clientY) {
			if (!this.dragState || !this.dragState.ghost) {
				return;
			}
			const { ghost, offsetX, offsetY } = this.dragState;
			const left = clientX - Math.min(offsetX, ghost.offsetWidth - 20);
			const top = clientY - Math.min(offsetY, ghost.offsetHeight - 20);
			ghost.style.left = `${left}px`;
			ghost.style.top = `${top}px`;
		}

		updateDropTarget(clientX, clientY) {
			if (!this.dragState || !this.dragState.ghost) {
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

		cancelDrag() {
			if (!this.dragState) {
				return;
			}

			const { didMove } = this.dragState;
			this.cleanupDrag();
			if (didMove) {
				this.suppressClick = true;
				window.setTimeout(() => {
					this.suppressClick = false;
				}, 0);
			}
		}

		onLostPointerCapture() {
			const state = this.dragState;
			if (!state) {
				return;
			}

			window.setTimeout(() => {
				if (this.dragState === state) {
					this.cancelDrag();
				}
			}, 0);
		}

		cleanupDrag() {
			if (!this.dragState) {
				return;
			}

			const state = this.dragState;
			const { source, ghost, target } = state;
			source.classList.remove('is-drag-source');
			if (target) {
				target.classList.remove('is-drop-target');
			}
			window.removeEventListener('pointermove', state.onMove);
			window.removeEventListener('pointerup', state.onUp);
			window.removeEventListener('pointercancel', state.onCancel);
			source.removeEventListener('lostpointercapture', state.onLostCapture);

			try {
				source.releasePointerCapture(state.pointerId);
			} catch (error) {
				// Capture may already be gone; nothing to release.
			}

			if (ghost) {
				ghost.remove();
			}
			this.dragState = null;
			this.sweepOrphans();
		}

		sweepOrphans() {
			const activeGhost = this.dragState ? this.dragState.ghost : null;
			document.querySelectorAll('.tbtmg-drag-ghost').forEach((ghost) => {
				if (ghost.dataset.tbtmgOwner === this.instanceId && ghost !== activeGhost) {
					ghost.remove();
				}
			});

			const activeSource = this.dragState ? this.dragState.source : null;
			const activeTarget = this.dragState ? this.dragState.target : null;
			this.container.querySelectorAll('.tbtmg-card.is-drag-source').forEach((card) => {
				if (card !== activeSource) {
					card.classList.remove('is-drag-source');
				}
			});
			this.container.querySelectorAll('.tbtmg-card.is-drop-target').forEach((card) => {
				if (card !== activeTarget) {
					card.classList.remove('is-drop-target');
				}
			});
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
