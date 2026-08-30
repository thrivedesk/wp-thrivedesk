import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

export const ThriveDeskConversationData = () => {
	const [contactID, setContactID] = useState('');
	const [conversations, setConversations] = useState([]);

	useEffect(() => {
		let requestUrl = new URLSearchParams(window.location.search);
		let params = requestUrl.get('path');
		setContactID(params.split('/')[2]);
	}, []);

	useEffect(() => {
		if (contactID !== '') {
			apiFetch({
				path: `thrivedesk/v1/conversations/contact/${contactID}`,
			}).then((data) => {
				setConversations(data);
			});
		}
	}, [contactID]);

	return (
		<div className='bwf-c-s-contact'>
			<div className='bwf-table contact-single-table'>
				<div className='bwf-table-table'>
					<table>
						<thead>
							<th className='bwf-table-header'>{__('ID', 'thrivedesk')}</th>
							<th className='bwf-table-header'>{__('Title', 'thrivedesk')}</th>
							<th className='bwf-table-header'>{__('Status', 'thrivedesk')}</th>
							<th className='bwf-table-header'>{__('Submitted at', 'thrivedesk')}</th>
							<th className='bwf-table-header'>{__('Action', 'thrivedesk')}</th>
						</thead>
						<tbody>
							{conversations.length > 0 ? (
								conversations.map((conversation) => {
									return (
										<tr>
											<td className='bwf-table-item'>
												{conversation.id}
											</td>
											<td className='bwf-table-item'>
												{conversation.title}
											</td>
											<td className='bwf-table-item'>
												{conversation.status}
											</td>
											<td className='bwf-table-item'>
												{conversation.submitted_at}
											</td>
											<td className='bwf-table-item'>
												<a
													className={
														'bwf-a-no-underline'
													}
													href={conversation.action}
													target='_blank'>
													{__('View Conversation', 'thrivedesk')}
												</a>
											</td>
										</tr>
									);
								})
							) : (
								<tr>
									<td
										className='bwf-table-empty-item'
										colSpan='5'>
										{__('No conversations found', 'thrivedesk')}
									</td>
								</tr>
							)}
						</tbody>
					</table>
				</div>
			</div>
		</div>
	);
};
