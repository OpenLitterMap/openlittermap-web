import{F as l}from"./FunctionalCalendar-BeULC0Z7.js";import{n}from"./app-CFZqWuKE.js";import{L as c}from"./vue-loading-B4M59h66.js";/* empty css                    */import{h as d}from"./moment-zH0z38ay.js";/* empty css            */const h={name:"FilterMyPhotos",components:{FunctionalCalendar:l},data(){return{periods:["created_at","datetime"],processing:!1,showCalendar:!1}},computed:{calendar(){return this.showCalendar?"dropdown is-active":"dropdown"},filters(){return this.$store.state.photos.filters},filter_by_calendar:{get(){return this.filters.calendar},set(e){this.$store.commit("filter_photos_calendar",{min:e.dateRange.start,max:e.dateRange.end}),e.dateRange.end&&this.getPhotos()}},filter_by_id:{get(){return this.filters.id},set(e){this.$store.commit("filter_photos",{key:"id",v:e})}},getSelectAllText(){return this.selectAll?this.$t("common.de-select-all"):this.$t("common.select-all")},period:{get(){return this.filters.period},set(e){this.$store.commit("filter_photos",{key:"period",v:e})}},selectAll:{get(){return this.$store.state.photos.selectAll},set(e){this.$store.commit("selectAllPhotos",e)}},showCalendarDates(){return this.filters.dateRange.start&&this.filters.dateRange.end?`${this.filters.dateRange.start} - ${this.filters.dateRange.end}`:this.$t("common.choose-dates")},spinner(){return this.processing?"fa fa-refresh fa-spin":"fa fa-refresh"},verifiedIndex:{get(){return this.filters.verified},set(e){this.$store.commit("filter_photos",{key:"verified",v:e})}}},methods:{getPeriod(e){return e||(e=this.period),this.$t("teams.dashboard.times."+e)},async getPhotos(){await this.$store.dispatch("GET_USERS_FILTERED_PHOTOS")},getVerifiedText(e){return e===0?this.$t("common.not-verified"):this.$t("common.verified")},search(){this.processing=!0,this.timeout&&clearTimeout(this.timeout),this.timeout=setTimeout(async()=>{await this.getPhotos(),this.processing=!1},500)},toggleAll(){this.$store.commit("selectAllPhotos",this.selectAll)},toggleCalendar(){this.showCalendar=!this.showCalendar}}};var m=function(){var t=this,s=t._self._c;return s("div",{staticClass:"flex mb1 filter-my-photos"},[s("router-link",{attrs:{to:"/tag"}},[s("button",{staticClass:"button is-primary"},[t._v("Tag individually")])]),s("div",{staticClass:"field mb0 pt0"},[s("div",{staticClass:"control has-icons-left"},[s("input",{directives:[{name:"model",rawName:"v-model",value:t.filter_by_id,expression:"filter_by_id"}],staticClass:"input w10",attrs:{placeholder:t.$t("common.search-by-id")},domProps:{value:t.filter_by_id},on:{input:[function(o){o.target.composing||(t.filter_by_id=o.target.value)},t.search]}}),s("span",{staticClass:"icon is-small is-left z-index-0"},[s("i",{class:t.spinner})])])]),s("button",{staticClass:"button is-primary select-all-photos",on:{click:t.toggleAll}},[t._v(" "+t._s(t.getSelectAllText)+" ")]),s("div",{class:t.calendar},[s("div",{staticClass:"dropdown-trigger"},[s("button",{staticClass:"button dropdownButtonLeft",on:{click:t.toggleCalendar}},[s("span",[t._v(t._s(t.showCalendarDates))])])]),s("div",{staticClass:"dropdown-menu"},[s("div",{staticClass:"dropdown-content calendar-box"},[s("FunctionalCalendar",{ref:"calendar",attrs:{"day-names":t.$t("common.day-names"),"month-names":t.$t("common.month-names"),"short-month-names":t.$t("common.short-month-names"),"change-month-function":!0,"change-year-function":!0,"is-date-range":!0,"date-format":"yyyy/mm/dd"},on:{selectedDaysCount:t.toggleCalendar},model:{value:t.filter_by_calendar,callback:function(o){t.filter_by_calendar=o},expression:"filter_by_calendar"}})],1)])]),s("div",[s("select",{directives:[{name:"model",rawName:"v-model",value:t.period,expression:"period"}],staticClass:"input",on:{change:[function(o){var a=Array.prototype.filter.call(o.target.options,function(i){return i.selected}).map(function(i){var r="_value"in i?i._value:i.value;return r});t.period=o.target.multiple?a:a[0]},t.getPhotos]}},t._l(t.periods,function(o){return s("option",{domProps:{value:o}},[t._v(t._s(t.getPeriod(o)))])}),0)])],1)},u=[],p=n(h,m,u,!1,null,"a15747e1");const g=p.exports,_={name:"PhotoDetailsPopup",computed:{photo(){const e=this.$store.state.photos.showDetailsPhotoId;return this.$store.state.photos.bulkPaginate.data.find(t=>t.id===e)}},methods:{getCategoryName(e){return this.$i18n.t(`litter.categories.${e}`)},getTagName(e,t){return this.$i18n.t(`litter.${e}.${t}`)},removeTag(e,t){this.$store.commit("removeTagFromPhoto",{photoId:this.photo.id,category:e,tag:t})},clearCustomTag(e){this.$store.commit("removeCustomTagFromPhoto",{photoId:this.photo.id,customTag:e})},togglePickedUp(){this.$store.commit("setPhotoPickedUp",{photoId:this.photo.id,picked_up:!this.photo.picked_up})}}};var f=function(){var t=this,s=t._self._c;return t.photo?s("div",[s("div",[s("div",{staticClass:"top-row"},[s("div",{staticClass:"switch-container"},[s("p",{staticClass:"mr-2"},[s("strong",[t._v(t._s(t.$t("tags.picked-up-title")))])]),s("label",{staticClass:"switch"},[s("input",{attrs:{type:"checkbox"},domProps:{checked:t.photo.picked_up},on:{change:t.togglePickedUp}}),s("span",{staticClass:"slider round"})])])]),s("div",{staticClass:"close-popup",on:{click:function(o){return t.$emit("close")}}},[s("i",{staticClass:"fa fa-times"})])]),t.photo.custom_tags&&t.photo.custom_tags.length||Object.keys(t.photo.tags).length?s("div",{staticClass:"photo-tags-container"},[t.photo.custom_tags&&t.photo.custom_tags.length?s("div",[s("p",{staticClass:"has-text-centered"},[t._v(t._s(t.$t("tags.custom-tags")))]),s("transition-group",{staticClass:"tags-list",attrs:{name:"list",tag:"div"}},t._l(t.photo.custom_tags,function(o){return s("div",{key:o,staticClass:"litter-tag"},[s("span",{staticClass:"close",on:{click:function(a){return a.preventDefault(),a.stopPropagation(),t.clearCustomTag(o)}}},[s("i",{staticClass:"fa fa-times"})]),s("p",{staticClass:"has-text-white"},[t._v(t._s(o))])])}),0)],1):t._e(),s("transition-group",{attrs:{name:"categories",tag:"div"}},t._l(Object.keys(t.photo.tags||{}),function(o){return s("div",{key:o},[s("p",{staticClass:"has-text-centered"},[t._v(t._s(t.getCategoryName(o)))]),s("transition-group",{staticClass:"tags-list",attrs:{name:"list",tag:"div"}},t._l(Object.keys(t.photo.tags[o]),function(a){return s("div",{key:a,staticClass:"litter-tag"},[s("span",{staticClass:"close",on:{click:function(i){return i.preventDefault(),i.stopPropagation(),t.removeTag(o,a)}}},[s("i",{staticClass:"fa fa-times"})]),s("p",{staticClass:"has-text-white"},[t._v(" "+t._s(t.getTagName(o,a))+": "+t._s(t.photo.tags[o][a])+" ")])])}),0)],1)}),0)],1):t._e()]):t._e()},v=[],C=n(_,f,v,!1,null,"c3ddc111");const $=C.exports,b={name:"BulkTag",components:{Loading:c,FilterMyPhotos:g,PhotoDetailsPopup:$},data(){return{processing:!1}},async mounted(){this.$store.commit("resetPhotoState"),await this.$store.dispatch("LOAD_MY_PHOTOS")},computed:{calendar(){return this.showCalendar?"dropdown is-active mr1":"dropdown mr1"},paginate(){return this.$store.state.photos.bulkPaginate},photos(){return this.paginate.data},selectedCount(){return this.$store.state.photos.selectedCount},hasAddedTags(){return this.processing?!1:this.photos.filter(this.photoIsTagged).length}},methods:{addTags(){this.$store.commit("showModal",{type:"AddManyTagsToManyPhotos",title:this.$t("common.add-many-tags")})},async submit(){this.hasAddedTags&&(this.processing=!0,await this.$store.dispatch("BULK_TAG_PHOTOS"),this.processing=!1,setTimeout(()=>{window.location.reload()},2e3))},async applyTags(){if(!this.hasAddedTags)return;this.processing=!0;const e=this.photos.filter(this.photoIsTagged);for(let t in e){Object.entries(e[t].tags??{}).forEach(([o,a])=>{Object.entries(a).forEach(([i,r])=>{this.$store.commit("addTag",{photoId:e[t].id,category:o,tag:i,quantity:r})})});const s=e[t].custom_tags??[];for(const o in s)this.$store.commit("addCustomTag",{photoId:e[t].id,customTag:s[o]})}setTimeout(()=>{this.processing=!1,this.$router.push("/tag")},300)},deletePhotos(){this.$store.commit("showModal",{type:"ConfirmDeleteManyPhotos",title:this.$t("common.confirm-delete")})},togglePhotoDetailsPopup(e){const t=this.showPhotoDetails(e)?null:e.id;this.$store.commit("setPhotoToShowDetails",t)},getDate(e){return d(e).format("LL")},previous(){this.$store.dispatch("PREVIOUS_PHOTOS_PAGE")},next(){this.$store.dispatch("NEXT_PHOTOS_PAGE")},select(e){this.$store.commit("togglePhotoSelected",e)},photoIsTagged(e){var o;const t=e.tags&&Object.keys(e.tags).length,s=(o=e.custom_tags)==null?void 0:o.length;return t||s},showPhotoDetails(e){return this.$store.state.photos.showDetailsPhotoId===e.id}}};var y=function(){var t=this,s=t._self._c;return s("section",{staticClass:"hero fullheight bulk-tag"},[s("loading",{directives:[{name:"show",rawName:"v-show",value:t.processing,expression:"processing"}],attrs:{"is-full-page":!0},model:{value:t.processing,callback:function(o){t.processing=o},expression:"processing"}}),s("FilterMyPhotos"),s("div",{staticClass:"my-photos-grid-container"},t._l(t.photos,function(o){return s("div",{key:o.id,staticClass:"my-grid-photo"},[s("img",{directives:[{name:"img",rawName:"v-img",value:{sourceButton:!0,openOn:"dblclick"},expression:"{sourceButton: true, openOn: 'dblclick'}"}],staticClass:"litter",attrs:{src:o.filename},on:{click:function(a){return t.select(o.id)}}}),o.selected?s("div",{staticClass:"grid-checkmark"},[t._m(0,!0)]):t._e(),t.photoIsTagged(o)?s("div",{staticClass:"grid-tagged tooltip",on:{click:function(a){return a.preventDefault(),a.stopPropagation(),t.togglePhotoDetailsPopup(o)}}},[t._m(1,!0)]):t._e(),s("transition",{attrs:{name:"fade"}},[t.showPhotoDetails(o)?s("div",{staticClass:"photo-tags"},[s("PhotoDetailsPopup",{on:{close:function(a){return t.togglePhotoDetailsPopup(o)}}})],1):t._e()])],1)}),0),s("div",{staticClass:"bottom-actions"},[s("div",{staticClass:"bottom-navigation"},[s("button",{directives:[{name:"show",rawName:"v-show",value:this.paginate.prev_page_url,expression:"this.paginate.prev_page_url"}],staticClass:"button is-medium mr1",on:{click:t.previous}},[t._v(t._s(t.$t("common.previous")))]),s("button",{directives:[{name:"show",rawName:"v-show",value:this.paginate.next_page_url,expression:"this.paginate.next_page_url"}],staticClass:"button is-medium mr1",on:{click:t.next}},[t._v(t._s(t.$t("common.next")))]),s("div",{staticClass:"photos-info"},[t._m(2),s("div",[t._v(t._s(t.$t("profile.dashboard.bulk-tag-dblclick-info")))])])]),s("div",[s("button",{staticClass:"button is-medium is-primary",attrs:{disabled:t.selectedCount===0},on:{click:t.addTags}},[t._v(t._s(t.$t("common.add-tags")))])]),s("div",{staticClass:"bottom-right-actions"},[s("button",{staticClass:"button is-medium is-primary",attrs:{disabled:!t.hasAddedTags},on:{click:t.applyTags}},[t._v("Add and tag one by one")]),s("button",{staticClass:"button is-medium is-primary",attrs:{disabled:!t.hasAddedTags},on:{click:t.submit}},[t._v(t._s(t.$t("common.submit")))])])])],1)},P=[function(){var e=this,t=e._self._c;return t("div",{staticClass:"tag-icon"},[t("i",{staticClass:"fa fa-check"})])},function(){var e=this,t=e._self._c;return t("div",{staticClass:"tag-icon"},[t("span",{staticClass:"tooltip-text is-size-7"},[e._v("View tags")]),t("i",{staticClass:"fa fa-tags"})])},function(){var e=this,t=e._self._c;return t("div",{staticClass:"info-icon"},[t("i",{staticClass:"fa fa-info"})])}],T=n(b,y,P,!1,null,"b14971f8");const I=T.exports;export{I as default};