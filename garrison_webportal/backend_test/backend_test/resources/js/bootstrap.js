// filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/resources/js/bootstrap.js

window._ = require('lodash');

try {
    window.Popper = require('@popperjs/core').default;
    window.$ = window.jQuery = require('jquery');

    require('bootstrap');
} catch (e) {}