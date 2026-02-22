export default {
	install(Vue, options) {
		Vue.prototype.can = function(value){
			var permissions = window.Laravel.jsPermissions.permissions;
			var _return = false;
			if(!Array.isArray(permissions)){
				return false;
			}
			if(value.includes('|')){
				value.split('|').forEach(function (item) {
					if(permissions.includes(item.trim())){
						_return = true;
					}
				});
			}else if(value.includes('&')){
				_return = true;
				value.split('&').forEach(function (item) {
					if(!permissions.includes(item.trim())){
						_return = false;
					}
				});
			}else{
				_return = permissions.includes(value.trim());
			}
			return _return;
		}
		
		Vue.prototype.is = function(value){
			var roles = window.Laravel.jsPermissions.roles;
			var _return = false;
			if(!Array.isArray(roles)){
				return false;
			}
			if(value.includes('|')){
				value.split('|').forEach(function (item) {
					if(roles.includes(item.trim())){
						_return = true;
					}
				});
			}else if(value.includes('&')){
				_return = true;
				value.split('&').forEach(function (item) {
					if(!roles.includes(item.trim())){
						_return = false;
					}
				});
			}else{
				_return = roles.includes(value.trim());
			}
			return _return;
		}
	}
}