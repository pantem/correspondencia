import axios from 'axios';

axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['Accept'] = 'application/json';

window.axios = axios;
