(()=>{"use strict";var e={n:t=>{var a=t&&t.__esModule?()=>t.default:()=>t;return e.d(a,{a}),a},d:(t,a)=>{for(var l in a)e.o(a,l)&&!e.o(t,l)&&Object.defineProperty(t,l,{enumerable:!0,get:a[l]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.hooks,a=window.wp.i18n,l=window.wp.element,n=window.wp.apiFetch;var c=e.n(n);const s=()=>{const[e,t]=(0,l.useState)(""),[a,n]=(0,l.useState)([]);return(0,l.useEffect)((()=>{let e=new URLSearchParams(window.location.search).get("path");t(e.split("/")[2])}),[]),(0,l.useEffect)((()=>{""!==e&&c()({path:`thrivedesk/v1/conversations/contact/${e}`}).then((e=>{n(e)}))}),[e]),(0,l.createElement)("div",{className:"bwf-c-s-contact"},(0,l.createElement)("div",{className:"bwf-table contact-single-table"},(0,l.createElement)("div",{className:"bwf-table-table"},(0,l.createElement)("table",null,(0,l.createElement)("thead",null,(0,l.createElement)("th",{className:"bwf-table-header"},"ID"),(0,l.createElement)("th",{className:"bwf-table-header"},"Title"),(0,l.createElement)("th",{className:"bwf-table-header"},"Status"),(0,l.createElement)("th",{className:"bwf-table-header"},"Submitted at"),(0,l.createElement)("th",{className:"bwf-table-header"},"Action")),(0,l.createElement)("tbody",null,a.length>0?a.map((e=>(0,l.createElement)("tr",null,(0,l.createElement)("td",{className:"bwf-table-item"},e.id),(0,l.createElement)("td",{className:"bwf-table-item"},e.title),(0,l.createElement)("td",{className:"bwf-table-item"},e.status),(0,l.createElement)("td",{className:"bwf-table-item"},e.submitted_at),(0,l.createElement)("td",{className:"bwf-table-item"},(0,l.createElement)("a",{className:"bwf-a-no-underline",href:e.action,target:"_blank"},"View Conversation"))))):(0,l.createElement)("tr",null,(0,l.createElement)("td",{className:"bwf-table-empty-item",colSpan:"5"},"No conversations found")))))))};(0,t.addFilter)("bwfanAddTabOnSingleContact","bwfan",(e=>(e.push({key:"thrivedesk",name:(0,a.__)("ThriveDesk","wp-marketing-automations-crm")}),e))),(0,t.addFilter)("bwfanAddSingleContactCustomTabData","bwfan",((e,t)=>("thrivedesk"===t&&(e=s),e)))})();