import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { ThriveDeskConversationData } from './ThriveDeskConversationData';

addFilter('bwfanAddTabOnSingleContact', 'bwfan', (tabList) => {
	tabList.push({
		key: 'thrivedesk',
		name: __('ThriveDesk', 'wp-marketing-automations-crm'),
	});
	return tabList;
});

addFilter('bwfanAddSingleContactCustomTabData', 'bwfan', (data, tab) => {
	if (tab === 'thrivedesk') {
		data = ThriveDeskConversationData;
	}
	return data;
});
