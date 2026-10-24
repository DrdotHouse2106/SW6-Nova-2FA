import './service/otp-interceptor';
import './service/confirm-interceptor';
import './component/nova-two-factor-enforce';
import './extension/sw-desktop';
import './component/nova-two-factor-card';
import './component/nova-two-factor-profile-card';
import './component/nova-two-factor-user-card';
import './extension/sw-customer-detail-base';
import './extension/sw-login-login';
import './extension/sw-profile-index-general';
import './extension/sw-users-permissions-user-detail';
import './nova-two-factor.scss';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
