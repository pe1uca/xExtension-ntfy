
const sliderObserverCallback = (mutationList, observer) => {
  for (const mutation of mutationList) {
    if (mutation.type !== "childList") continue;

	for(const node of mutation.addedNodes) {
		if (node.id !== 'feed_update') continue;
		processFeddUpdateElement(node);
	}
  }
};

const processFeddUpdateElement = async (element) => {
	const feedId = element.getAttribute('data-feed-id');
	const informationFieldset = element.getElementsByTagName('fieldset')[0];
	const csrf = document.getElementsByName('_csrf')[0];

	const divNtfy = document.createElement('div');
	divNtfy.innerHTML = context.extensions.ntfy_feed_config_html;
	informationFieldset.insertAdjacentElement('afterend', divNtfy);
	const submitBtn = divNtfy.querySelector('#submit_ntfy');
	const topic = divNtfy.querySelector('#topic');

	const feedData = context.extensions.ntfy_feeds[feedId] ?? {};
	topic.value = feedData['topic'] ?? '';

	submitBtn.addEventListener('click', async (e) => {
		e.preventDefault();
		e.stopPropagation();

		const data = new FormData();
		data.set('_csrf', csrf.value);
		data.set('ajax', true);
		data.set('feed_id', feedId);
		data.set('topic', topic.value);
		const res = await fetch(
			`/i/?c=extension&a=configure&e=${context.extensions.ntfy_ext_name}&ntfy=feed`, 
			{
				method: 'post',
				body: data
			}
		);
		
		context.extensions.ntfy_feeds[feedId] = {
			'topic': topic.value
		};
	});
}

(async () => {
	let sliderContent = document.getElementById('slider-content');
	while (sliderContent === null) {
		console.log('waiting');
		await new Promise(r => setTimeout(r, 100));
		sliderContent = document.getElementById('slider-content');
	}

	const addObserver = () => {
		const observer = new MutationObserver(sliderObserverCallback);
		observer.observe(sliderContent, { childList: true });
	}

	if (document.readyState && document.readyState !== 'loading' && typeof window.context !== 'undefined' && typeof window.context.extensions !== 'undefined') {
		addObserver();
	} else {
		document.addEventListener('freshrss:globalContextLoaded', addObserver, false);
	}
})();