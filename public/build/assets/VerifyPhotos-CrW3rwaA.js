import{L as n}from"./vue-loading-B4M59h66.js";/* empty css                    */import{h as l}from"./moment-zH0z38ay.js";import{n as c,A as d,T as h}from"./app-CFZqWuKE.js";import{R as p}from"./RecentTags-CzPQzStk.js";/* empty css            */const u={name:"VerifyPhotos",components:{Loading:n,AddTags:d,Tags:h,RecentTags:p},async created(){this.loading=!0,await this.$store.dispatch("GET_NEXT_ADMIN_PHOTO"),this.loading=!1},data(){return{loading:!0,processing:!1,btn:"button is-large is-success",deleteButton:"button is-large is-danger mb1 tooltip",deleteVerify:"button is-large is-warning mb1 tooltip",selectedCountry:"",searchPhotoId:0,filterMyOwnPhotos:!1}},computed:{checkUpdateTagsDisabled(){return!!(this.processing||this.$store.state.litter.hasAddedNewTag===!1)},delete_button(){return this.processing?this.deleteButton+" is-loading":this.deleteButton},delete_verify_button(){return this.processing?this.deleteVerify+" is-loading":this.deleteVerify},photo(){return this.$store.state.admin.photo},photosNotProcessed(){return this.$store.state.admin.not_processed},photosAwaitingVerification(){return this.$store.state.admin.awaiting_verification},countriesWithPhotos(){return this.$store.state.admin.countriesWithPhotos},uploadedTime(){return l(this.photo.created_at).format("LLL")},verify_correct_button(){return this.processing?this.btn+" is-loading":this.btn},hasRecentTags(){return Object.keys(this.$store.state.litter.recentTags).length>0||this.$store.state.litter.recentCustomTags.length}},methods:{async adminDelete(o){this.processing=!0,await this.$store.dispatch("ADMIN_DELETE_IMAGE"),this.processing=!1},clearTags(){this.$store.commit("setAllTagsToZero",this.photo.id)},async filterByCountry(){this.loading=!0,this.$store.commit("setFilterByCountry",this.selectedCountry),await this.$store.dispatch("GET_NEXT_ADMIN_PHOTO"),this.loading=!1},async findPhotoById(){this.loading=!0,await this.$store.dispatch("ADMIN_FIND_PHOTO_BY_ID",this.searchPhotoId),this.loading=!1},async goBackOnePhoto(){this.processing=!0,await this.$store.dispatch("ADMIN_GO_BACK_ONE_PHOTO",{filterMyOwnPhotos:this.filterMyOwnPhotos,photoId:this.photo.id}),this.processing=!1},async resetTags(){this.processing=!0,await this.$store.dispatch("ADMIN_RESET_TAGS"),this.processing=!1},async verifyCorrect(){this.processing=!0,await this.$store.dispatch("ADMIN_VERIFY_CORRECT"),this.processing=!1},async verifyDelete(){this.processing=!0,await this.$store.dispatch("ADMIN_VERIFY_DELETE"),this.processing=!1},async updateNewTags(){this.processing=!0,await this.$store.dispatch("ADMIN_UPDATE_WITH_NEW_TAGS"),this.processing=!1},async skipPhoto(){this.loading=!0,this.$store.commit("setSkippedPhotos",this.$store.state.admin.skippedPhotos+1),await this.$store.dispatch("GET_NEXT_ADMIN_PHOTO"),this.loading=!1}}};var g=function(){var t=this,s=t._self._c;return s("div",[s("div",{staticClass:"has-background-grey-light has-text-centered py-2 admin-filters"},[s("p",{staticClass:"has-text-weight-bold"},[t._v("Filter photos by:")]),s("div",{staticClass:"control ml-4"},[s("div",{staticClass:"select"},[s("select",{directives:[{name:"model",rawName:"v-model",value:t.selectedCountry,expression:"selectedCountry"}],on:{change:[function(e){var a=Array.prototype.filter.call(e.target.options,function(i){return i.selected}).map(function(i){var r="_value"in i?i._value:i.value;return r});t.selectedCountry=e.target.multiple?a:a[0]},t.filterByCountry]}},[s("option",{attrs:{value:""}},[t._v("All Countries")]),t._l(t.countriesWithPhotos,function(e){return s("option",{key:e.id,domProps:{value:e.id}},[t._v(t._s(e.country)+" ("+t._s(e.total)+")")])})],2)])])]),s("div",{staticClass:"container is-fluid mt3"},[t.loading?s("loading",{attrs:{active:t.loading,"is-full-page":!0},on:{"update:active":function(e){t.loading=e}}}):s("div",[this.photosAwaitingVerification===0&&this.photosNotProcessed===0?s("div",[s("p",{staticClass:"title is-3"},[t._v("All done.")])]):t.photo?s("div",[s("div",{staticClass:"columns"},[s("div",{staticClass:"column is-3"},[s("p",[t._v("Search by ID. Press Enter to Search.")]),s("input",{directives:[{name:"model",rawName:"v-model",value:t.searchPhotoId,expression:"searchPhotoId"}],staticClass:"input",attrs:{type:"number",placeholder:"Enter ID"},domProps:{value:t.searchPhotoId},on:{keydown:function(e){return!e.type.indexOf("key")&&t._k(e.keyCode,"enter",13,e.key,"Enter")?null:t.findPhotoById.apply(null,arguments)},input:function(e){e.target.composing||(t.searchPhotoId=e.target.value)}}})])]),s("div",{staticClass:"columns"},[s("div",{staticClass:"column has-text-centered"},[s("p",{staticClass:"subtitle is-5"},[t._v("Uploaded, not tagged: "+t._s(this.photosNotProcessed))]),s("p",{staticClass:"subtitle is-5"},[t._v("Tagged, awaiting verification: "+t._s(this.photosAwaitingVerification))]),s("div",{staticClass:"mt-5"},[s("button",{class:t.delete_verify_button,attrs:{disabled:t.processing},on:{click:t.verifyDelete}},[s("span",{staticClass:"tooltip-text is-size-6"},[t._v("Accept data, verify, but delete the image.")]),t._v(" Verify & Delete ")]),s("button",{class:t.delete_button,attrs:{disabled:t.processing},on:{click:t.adminDelete}},[s("span",{staticClass:"tooltip-text is-size-6"},[t._v("Delete the image.")]),t._v(" DELETE ")])]),t.hasRecentTags?s("div",{staticClass:"recent-tags control has-text-centered has-background-light px-4 py-4"},[s("RecentTags",{staticClass:"mb-5",attrs:{"photo-id":t.photo.id}})],1):t._e()]),s("div",{staticClass:"column is-half",staticStyle:{"text-align":"center"}},[s("h1",{staticClass:"title is-2 has-text-centered"},[t._v(" #"+t._s(parseInt(this.photo.id).toLocaleString())+" Uploaded "+t._s(this.uploadedTime)+" ")]),s("p",[t._v(" From: "),s("span",[t._v("@"+t._s(this.photo.user.username)+" #"+t._s(this.photo.user.id))])]),s("p",[t._v(" Verification count: "+t._s(this.photo.user.user_verification_count)+"% ")]),s("p",{staticClass:"subtitle is-5 has-text-centered mb-8"},[t._v(" "+t._s(this.photo.display_name)+" ")]),s("img",{directives:[{name:"img",rawName:"v-img",value:{sourceButton:!0},expression:"{sourceButton: true}"}],staticClass:"verify-image",attrs:{src:this.photo.filename}}),t.photo.verification===.1?s("div",{staticClass:"has-text-centered mb1"},[s("button",{class:t.verify_correct_button,attrs:{disabled:t.processing},on:{click:t.verifyCorrect}},[t._v("VERIFY CORRECT")]),s("button",{staticClass:"button is-large is-danger",attrs:{disabled:t.processing},on:{click:t.resetTags}},[t._v("FALSE")])]):t._e(),s("div",{staticClass:"columns"},[s("div",{staticClass:"column is-two-thirds is-offset-2"},[s("add-tags",{attrs:{admin:!0,id:t.photo.id}})],1)]),s("div",{staticStyle:{"padding-top":"1em","text-align":"center"}},[s("button",{staticClass:"button is-large is-warning",on:{click:t.goBackOnePhoto}},[t._v(" Go Back 1 photo ")]),s("button",{staticClass:"button is-large is-success mb1 tooltip",class:t.processing?"is-loading":"",attrs:{disabled:t.checkUpdateTagsDisabled},on:{click:t.updateNewTags}},[s("span",{staticClass:"tooltip-text is-size-6"},[t._v("Update the image and save the new data.")]),t._v(" Update with new tags ")]),s("button",{staticClass:"button is-large is-info tooltip mb-1",attrs:{disabled:t.processing},on:{click:t.skipPhoto}},[s("span",{staticClass:"tooltip-text is-size-6"},[t._v("Skip this photo and verify the next one.")]),t._v(" Skip ")])]),s("div",{staticClass:"switch-container"},[s("p",{staticClass:"mr-2"},[s("strong",[t._v("Search your photos only")])]),s("label",{staticClass:"switch"},[s("input",{attrs:{type:"checkbox"},domProps:{checked:t.filterMyOwnPhotos},on:{change:function(e){t.filterMyOwnPhotos=!t.filterMyOwnPhotos}}}),s("span",{staticClass:"slider round"})])])]),s("div",{staticClass:"column has-text-centered",staticStyle:{position:"relative"}},[s("Tags",{attrs:{"photo-id":t.photo.id,admin:!0}}),s("div",{staticStyle:{"padding-top":"3em"}},[s("button",{staticClass:"button is-medium is-dark tooltip",on:{click:t.clearTags}},[s("span",{staticClass:"tooltip-text is-size-6"},[t._v("To undo this, just refresh the page.")]),t._v(" Clear user input ")])])],1)])]):s("div",[s("p",{staticClass:"title is-3"},[t._v("All photos for your selection are done.")]),s("p",{staticClass:"subtitle is-5"},[t._v("You can refresh the page to view skipped photos.")])])])],1)])},_=[],v=c(u,g,_,!1,null,"4a9be0c7");const w=v.exports;export{w as default};