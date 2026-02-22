// Original package and full credit goes to https://github.com/adammertel/Leaflet.Control.Select
// Code is copy-pasted here since the package does not export any objects
// therefore making it impossible to be imported from node_modules
// Version used: v0.2.0 on March 27, 2020

import L from 'leaflet';

export default {
    initialize: function () {
        L.Control.Select = L.Control.extend({
            options: {
                position: "topright",

                iconMain: "≡",
                iconChecked: "◉", // "☑"
                iconUnchecked: "ⵔ", //"❒",
                iconGroupChecked: "▶",
                iconGroupUnchecked: "⊳",

                multi: false,

                items: [], // {value: 'String', 'label': 'String', items?: [items]}
                id: "",
                selectedDefault: false,
                additionalClass: "",

                onOpen: () => {},
                onClose: () => {},
                onGroupOpen: itemGroup => {},
                onSelect: item => {}
            },

            initialize(options) {
                this.menus = [];
                L.Util.setOptions(this, options);
                const opts = this.options;

                this.options.items.forEach(item => {
                    if (!item.label) {
                        item.label = item.value;
                    }
                });

                if (opts.multi) {
                    opts.selectedDefault =
                        opts.selectedDefault instanceof Array ? opts.selectedDefault : [];
                } else {
                    opts.selectedDefault =
                        opts.selectedDefault ||
                        (opts.items instanceof Array && opts.items.length > 0
                            ? opts.items[0].value
                            : false);
                }

                console.log(opts.selectedDefault);

                this.state = {
                    selected: opts.selectedDefault, // false || multi ? {value} : [{value}]
                    open: false // false || 'top' || {value}
                };

                // assigning parents to items
                const assignParent = item => {
                    if (this._isGroup(item)) {
                        item.items.map(item2 => {
                            item2.parent = item.value;
                            assignParent(item2);
                        });
                    }
                };

                this.options.items.map(item => {
                    item.parent = "top";
                    assignParent(item);
                });

                // assigning children to items
                const getChildren = item => {
                    let children = [];
                    if (this._isGroup(item)) {
                        item.items.map(item2 => {
                            children.push(item2.value);
                            children = children.concat(getChildren(item2));
                        });
                    }
                    return children;
                };

                const assignChildrens = item => {
                    item.children = getChildren(item);

                    if (this._isGroup(item)) {
                        item.items.map(item2 => {
                            assignChildrens(item2);
                        });
                    }
                };

                this.options.items.map(item => {
                    assignChildrens(item);
                });
            },

            onAdd(map) {
                this.map = map;
                const opts = this.options;

                this.container = L.DomUtil.create(
                    "div",
                    "leaflet-control leaflet-bar leaflet-control-select"
                );
                this.container.setAttribute("id", opts.id);

                const icon = L.DomUtil.create(
                    "a",
                    "leaflet-control-button ",
                    this.container
                );
                icon.innerHTML = opts.iconMain;

                map.on("click", this._hideMenu, this);

                L.DomEvent.on(icon, "click", L.DomEvent.stop);
                L.DomEvent.on(icon, "click", this._iconClicked, this);

                L.DomEvent.disableClickPropagation(this.container);
                L.DomEvent.disableScrollPropagation(this.container);

                this.render();
                return this.container;
            },

            _emit(action, data) {
                const newState = {};

                switch (action) {
                case "ITEM_SELECT":
                    if (this.options.multi) {
                        newState.selected = this.state.selected.slice();

                        if (this.state.selected.includes(data.item.value)) {
                            newState.selected = newState.selected.filter(
                                s => s !== data.item.value
                            );
                        } else {
                            newState.selected.push(data.item.value);
                        }
                    } else {
                        newState.selected = data.item.value;
                    }
                    newState.open = data.item.parent;
                    break;

                case "GROUP_OPEN":
                    newState.open = data.item.value;
                    break;

                case "GROUP_CLOSE":
                    newState.open = data.item.parent;
                    break;

                case "MENU_OPEN":
                    newState.open = "top";
                    break;

                case "MENU_CLOSE":
                    newState.open = false;
                    break;
                }

                this._setState(newState);
                this.render();
            },

            _setState(newState) {
                // events
                if (
                    this.options.onSelect &&
                    newState.selected &&
                    ((this.options.multi &&
                            newState.selected.length !== this.state.selected.length) ||
                        (!this.options.multi && newState.selected !== this.state.selected))
                ) {
                    this.options.onSelect(newState.selected);
                }

                if (
                    this.options.onGroupOpen &&
                    newState.open &&
                    newState.open !== this.state.open
                ) {
                    console.log("group open");
                    this.options.onGroupOpen(newState.open);
                }

                if (this.options.onOpen && newState.open === "top") {
                    this.options.onOpen();
                }

                if (this.options.onClose && !newState.open) {
                    this.options.onClose();
                }

                this.state = Object.assign(this.state, newState);
            },

            _isGroup(item) {
                return "items" in item;
            },

            _isSelected(item) {
                const sel = this.state.selected;
                if (sel) {
                    if (this._isGroup(item)) {
                        if ("children" in item) {
                            return this.options.multi
                                ? sel.find(s => item.children.includes(s))
                                : item.children.includes(sel);
                        } else {
                            return false;
                        }
                    }
                    return this.options.multi
                        ? sel.indexOf(item.value) > -1
                        : sel === item.value;
                } else {
                    return false;
                }
            },

            _isOpen(item) {
                const open = this.state.open;
                return open && (open === item.value || item.children.includes(open));
            },

            _hideMenu() {
                this._emit("MENU_CLOSE", {});
            },

            _iconClicked() {
                this._emit("MENU_OPEN", {});
            },

            _itemClicked(item) {
                if (this._isGroup(item)) {
                    this.state.open === item.value
                        ? this._emit("GROUP_CLOSE", { item })
                        : this._emit("GROUP_OPEN", { item });
                } else {
                    this._emit("ITEM_SELECT", { item });
                }
            },

            _renderRadioIcon(selected, contentDiv) {
                const radio = L.DomUtil.create("span", "radio icon", contentDiv);

                radio.innerHTML = selected
                    ? this.options.iconChecked
                    : this.options.iconUnchecked;
            },

            _renderGroupIcon(selected, contentDiv) {
                const group = L.DomUtil.create("span", "group icon", contentDiv);

                group.innerHTML = selected
                    ? this.options.iconGroupChecked
                    : this.options.iconGroupUnchecked;
            },

            _renderItem(item, menu) {
                const selected = this._isSelected(item);

                const p = L.DomUtil.create("div", "leaflet-control-select-menu-line", menu);
                const pContent = L.DomUtil.create(
                    "div",
                    "leaflet-control-select-menu-line-content",
                    p
                );
                const textSpan = L.DomUtil.create("span", "text", pContent);

                textSpan.innerHTML = item.label;

                if (this._isGroup(item)) {
                    this._renderGroupIcon(selected, pContent);

                    // adding classes to groups and opened group
                    L.DomUtil.addClass(p, "group");
                    this._isOpen(item) && L.DomUtil.addClass(p, "group-opened");

                    this._isOpen(item) && this._renderMenu(p, item.items);
                } else {
                    this._renderRadioIcon(selected, pContent);
                }

                L.DomEvent.addListener(pContent, "click", e => {
                    this._itemClicked(item);
                });

                return p;
            },

            _renderMenu(parent, items) {
                const menu = L.DomUtil.create(
                    "div",
                    "leaflet-control-select-menu leaflet-bar ",
                    parent
                );
                this.menus.push(menu);
                items.map(item => {
                    this._renderItem(item, menu);
                });
            },

            _clearMenus() {
                this.menus.map(menu => menu.remove());
                this.meus = [];
            },

            render() {
                this._clearMenus();
                if (this.state.open) {
                    this._renderMenu(this.container, this.options.items);
                }
            },

            /* public methods */
            close() {
                this._hideMenu();
            }
        });

        L.control.select = options => new L.Control.Select(options);
    }
}
