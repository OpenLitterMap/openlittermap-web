import{n as m}from"./app-CxBR0OFb.js";/* empty css            */const o={name:"MapsPrivacy",computed:{maps_name:{get(){return this.$store.getters.user.show_name_maps},set(c){this.$store.commit("changePrivacy",{column:"show_name_maps",v:c})}},maps_username:{get(){return this.$store.getters.user.show_username_maps},set(c){this.$store.commit("changePrivacy",{column:"show_username_maps",v:c})}}}};var l=function(){var e=this,a=e._self._c;return a("div",[a("h1",{staticClass:"title is-4"},[e._v(" "+e._s(e.$t("settings.privacy.maps"))+": ")]),a("div",{staticClass:"mb1"},[a("input",{directives:[{name:"model",rawName:"v-model",value:e.maps_name,expression:"maps_name"}],attrs:{id:"settings_maps_change_name",name:"settings_maps_change_name",type:"checkbox"},domProps:{checked:Array.isArray(e.maps_name)?e._i(e.maps_name,null)>-1:e.maps_name},on:{change:function(i){var s=e.maps_name,r=i.target,_=!!r.checked;if(Array.isArray(s)){var n=null,t=e._i(s,n);r.checked?t<0&&(e.maps_name=s.concat([n])):t>-1&&(e.maps_name=s.slice(0,t).concat(s.slice(t+1)))}else e.maps_name=_}}}),a("label",{staticClass:"checkbox",attrs:{for:"settings_maps_change_name"}},[e._v(" "+e._s(e.$t("settings.privacy.credit-name"))+" ")]),a("br"),a("input",{directives:[{name:"model",rawName:"v-model",value:e.maps_username,expression:"maps_username"}],attrs:{id:"settings_maps_change_username",name:"settings_maps_change_username",type:"checkbox"},domProps:{checked:Array.isArray(e.maps_username)?e._i(e.maps_username,null)>-1:e.maps_username},on:{change:function(i){var s=e.maps_username,r=i.target,_=!!r.checked;if(Array.isArray(s)){var n=null,t=e._i(s,n);r.checked?t<0&&(e.maps_username=s.concat([n])):t>-1&&(e.maps_username=s.slice(0,t).concat(s.slice(t+1)))}else e.maps_username=_}}}),a("label",{staticClass:"checkbox",attrs:{for:"settings_maps_change_username"}},[e._v(" "+e._s(e.$t("settings.privacy.credit-username"))+" ")])]),a("div",{staticClass:"mb1"},[e.maps_name&&e.maps_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" Both your name and username will appear on each image you upload to the maps. ")]):e.maps_name&&!e.maps_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.name-imgs-yes"))+" ")]):!e.maps_name&&e.maps_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.username-imgs-yes"))+" ")]):!e.maps_name&&!e.maps_username?a("h1",{staticClass:"failed-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.name-username-map-no"))+" ")]):e._e()])])},d=[],p=m(o,l,d,!1,null,null);const u=p.exports,y={name:"LeaderboardsPrivacy",computed:{leaderboard_name:{get(){return this.$store.getters.user.show_name},set(c){this.$store.commit("changePrivacy",{column:"show_name",v:c})}},leaderboard_username:{get(){return this.$store.getters.user.show_username},set(c){this.$store.commit("changePrivacy",{column:"show_username",v:c})}}}};var v=function(){var e=this,a=e._self._c;return a("div",[a("h1",{staticClass:"title is-4"},[e._v(" "+e._s(e.$t("settings.privacy.leaderboards"))+": ")]),a("div",{staticClass:"mb1"},[a("input",{directives:[{name:"model",rawName:"v-model",value:e.leaderboard_name,expression:"leaderboard_name"}],attrs:{id:"settings_privacy_leaderboards_name",name:"settings_privacy_leaderboards_name",type:"checkbox"},domProps:{checked:Array.isArray(e.leaderboard_name)?e._i(e.leaderboard_name,null)>-1:e.leaderboard_name},on:{change:function(i){var s=e.leaderboard_name,r=i.target,_=!!r.checked;if(Array.isArray(s)){var n=null,t=e._i(s,n);r.checked?t<0&&(e.leaderboard_name=s.concat([n])):t>-1&&(e.leaderboard_name=s.slice(0,t).concat(s.slice(t+1)))}else e.leaderboard_name=_}}}),a("label",{staticClass:"checkbox",attrs:{for:"settings_privacy_leaderboards_name"}},[e._v(" "+e._s(e.$t("settings.privacy.credit-my-name"))+" ")]),a("br"),a("input",{directives:[{name:"model",rawName:"v-model",value:e.leaderboard_username,expression:"leaderboard_username"}],attrs:{id:"settings_privacy_leaderboards_username",name:"settings_privacy_leaderboards_username",type:"checkbox"},domProps:{checked:Array.isArray(e.leaderboard_username)?e._i(e.leaderboard_username,null)>-1:e.leaderboard_username},on:{change:function(i){var s=e.leaderboard_username,r=i.target,_=!!r.checked;if(Array.isArray(s)){var n=null,t=e._i(s,n);r.checked?t<0&&(e.leaderboard_username=s.concat([n])):t>-1&&(e.leaderboard_username=s.slice(0,t).concat(s.slice(t+1)))}else e.leaderboard_username=_}}}),a("label",{staticClass:"checkbox",attrs:{for:"settings_privacy_leaderboards_username"}},[e._v(" "+e._s(e.$t("settings.privacy.credit-my-username"))+" ")])]),a("div",{staticClass:"mb1"},[e.leaderboard_name&&e.leaderboard_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" Both your name and username will appear on the Leaderboards. Good luck! ")]):e.leaderboard_name&&!e.leaderboard_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.name-leaderboards-yes"))+" ")]):!e.leaderboard_name&&e.leaderboard_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.username-leaderboards-yes"))+" ")]):!e.leaderboard_name&&!e.leaderboard_username?a("h1",{staticClass:"failed-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.name-username-leaderboards-no"))+" ")]):e._e()])])},h=[],g=m(y,v,h,!1,null,null);const b=g.exports,f={name:"CreatedByPrivacy",computed:{createdby_name:{get(){return this.$store.getters.user.show_name_createdby},set(c){this.$store.commit("changePrivacy",{column:"show_name_createdby",v:c})}},createdby_username:{get(){return this.$store.getters.user.show_username_createdby},set(c){this.$store.commit("changePrivacy",{column:"show_username_createdby",v:c})}}}};var C=function(){var e=this,a=e._self._c;return a("div",[a("h1",{staticClass:"title is-4"},[e._v(" "+e._s(e.$t("settings.privacy.created-by"))+": ")]),a("div",{staticClass:"mb1"},[a("input",{directives:[{name:"model",rawName:"v-model",value:e.createdby_name,expression:"createdby_name"}],attrs:{id:"settings_privacy_createdby_name",name:"settings_privacy_createdby_name",type:"checkbox"},domProps:{checked:Array.isArray(e.createdby_name)?e._i(e.createdby_name,null)>-1:e.createdby_name},on:{change:function(i){var s=e.createdby_name,r=i.target,_=!!r.checked;if(Array.isArray(s)){var n=null,t=e._i(s,n);r.checked?t<0&&(e.createdby_name=s.concat([n])):t>-1&&(e.createdby_name=s.slice(0,t).concat(s.slice(t+1)))}else e.createdby_name=_}}}),a("label",{staticClass:"checkbox",attrs:{for:"settings_privacy_createdby_name"}},[e._v(" "+e._s(e.$t("settings.privacy.credit-name"))+" ")]),a("br"),a("input",{directives:[{name:"model",rawName:"v-model",value:e.createdby_username,expression:"createdby_username"}],attrs:{id:"settings_privacy_createdby_username",name:"settings_privacy_createdby_username",type:"checkbox"},domProps:{checked:Array.isArray(e.createdby_username)?e._i(e.createdby_username,null)>-1:e.createdby_username},on:{change:function(i){var s=e.createdby_username,r=i.target,_=!!r.checked;if(Array.isArray(s)){var n=null,t=e._i(s,n);r.checked?t<0&&(e.createdby_username=s.concat([n])):t>-1&&(e.createdby_username=s.slice(0,t).concat(s.slice(t+1)))}else e.createdby_username=_}}}),a("label",{staticClass:"checkbox",attrs:{for:"settings_privacy_createdby_username"}},[e._v(" "+e._s(e.$t("settings.privacy.credit-username"))+" ")])]),a("div",{staticClass:"mb1"},[e.createdby_name&&e.createdby_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" Both your name and username will appear in the Created By section of any new locations you create by being the first to upload. ")]):e.createdby_name&&!e.createdby_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.name-locations-yes"))+" ")]):!e.createdby_name&&e.createdby_username?a("h1",{staticClass:"success-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.username-locations-yes"))+" ")]):!e.createdby_name&&!e.createdby_username?a("h1",{staticClass:"failed-privacy-text"},[e._v(" "+e._s(e.$t("settings.privacy.name-username-locations-yes"))+" ")]):e._e()])])},x=[],k=m(f,C,x,!1,null,null);const $=k.exports,P={name:"PreventOthersTaggingMyPhotos",computed:{prevent_others_tagging_my_photos:{get(){return this.$store.getters.user.prevent_others_tagging_my_photos},set(c){this.$store.commit("changePrivacy",{column:"prevent_others_tagging_my_photos",v:c})}}}};var A=function(){var e=this,a=e._self._c;return a("div",[a("h1",{staticClass:"title is-4"},[e._v(" Prevent others tagging my photos: ")]),a("div",{staticClass:"mb1"},[a("input",{directives:[{name:"model",rawName:"v-model",value:e.prevent_others_tagging_my_photos,expression:"prevent_others_tagging_my_photos"}],attrs:{id:"settings_privacy_prevent_others_tagging_my_photos",name:"settings_privacy_prevent_others_tagging_my_photos",type:"checkbox"},domProps:{checked:Array.isArray(e.prevent_others_tagging_my_photos)?e._i(e.prevent_others_tagging_my_photos,null)>-1:e.prevent_others_tagging_my_photos},on:{change:function(i){var s=e.prevent_others_tagging_my_photos,r=i.target,_=!!r.checked;if(Array.isArray(s)){var n=null,t=e._i(s,n);r.checked?t<0&&(e.prevent_others_tagging_my_photos=s.concat([n])):t>-1&&(e.prevent_others_tagging_my_photos=s.slice(0,t).concat(s.slice(t+1)))}else e.prevent_others_tagging_my_photos=_}}}),a("label",{staticClass:"checkbox",attrs:{for:"settings_privacy_prevent_others_tagging_my_photos"}},[e._v(" Prevent others tagging my photos ")])])])},w=[],N=m(P,A,w,!1,null,null);const B=N.exports,M={name:"Privacy",components:{PreventOthersTaggingMyPhotos:B,MapsPrivacy:u,LeaderboardsPrivacy:b,CreatedByPrivacy:$},data(){return{processing:!1}},methods:{async submit(){this.processing=!0,await this.$store.dispatch("SAVE_PRIVACY_SETTINGS"),this.processing=!1}}};var R=function(){var e=this,a=e._self._c;return a("div",{staticStyle:{"padding-left":"1em","padding-right":"1em"}},[a("h1",{staticClass:"title is-4"},[e._v(" "+e._s(e.$t("settings.privacy.change-privacy"))+" ")]),a("hr"),a("br"),a("div",{staticClass:"columns"},[a("div",{staticClass:"column one-third is-offset-1"},[a("div",{staticClass:"field"},[a("MapsPrivacy"),a("LeaderboardsPrivacy"),a("CreatedByPrivacy"),a("PreventOthersTaggingMyPhotos")],1),a("button",{staticClass:"button is-medium is-info",class:e.processing?"is-loading":"",attrs:{disabled:e.processing},on:{click:e.submit}},[e._v(" "+e._s(e.$t("settings.privacy.update"))+" ")])])])])},F=[],T=m(M,R,F,!1,null,null);const O=T.exports;export{O as default};