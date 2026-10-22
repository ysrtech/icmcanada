/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   BL
 * @package    BL_CustomGrid
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Searchable dropdown: one control that opens a panel holding a search field
 * and the options, for both single-value and multiple-selection fields.
 *
 * This replaces the vendored BVSelect library that used to render the
 * single-value case. That library never read the options already selected: it
 * looked for the "selected" HTML attribute with getAttribute("selected") == ""
 * and Magento writes selected="selected", so the test never matched and the
 * closed control always opened on its placeholder. A product whose publisher
 * was set showed "-- Please Select --" until someone touched the field, which
 * is indistinguishable from the value not having been saved. Its multiple mode
 * had the same blind spot, plus a label that only reported what was clicked in
 * the current session.
 *
 * The native <select> is the source of truth throughout: every choice writes
 * straight to it and fires a change event, so anything else bound to the field
 * behaves exactly as it would with the native control, and the closed control
 * is always redrawn from what the select actually holds.
 */

if (typeof(blcg) == 'undefined') {
    var blcg = {};
}

blcg.SelectWidget = {
    STORAGE_KEY: 'blcg.selectWidget',
    
    /** How many values to name in a closed multiple-selection control before summarising */
    MAX_NAMED: 3,
    
    /** Whether the shared document-level handlers have been bound */
    isListening: false,
    
    /**
     * @param string string
     * @return string
     */
    translate: function(string)
    {
        return (typeof(Translator) != 'undefined') ? Translator.translate(string) : string;
    },
    
    /**
     * @param string value
     * @return string
     */
    escape: function(value)
    {
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    },
    
    /**
     * @param Element select
     * @return bool
     */
    isEligible: function(select)
    {
        return !select.retrieve(this.STORAGE_KEY, null)
            && (select.readAttribute('blcg-searchable') !== '0')
            && (blcg.SearchableSelect.countSelectableOptions(select) > blcg.SearchableSelect.getMinOptions());
    },
    
    /**
     * Give a nameless empty option something to display.
     *
     * Both the closed control and the list row are drawn from the option's
     * text, so an option that is empty in both value and label - which is how
     * Magento writes the "no value" choice of a grid filter and of most
     * attribute dropdowns - renders as a blank box and an invisible row.
     * Naming the option fixes both at once.
     *
     * Options that already carry a label, such as "All Countries", are left
     * alone: they are clear as they are.
     *
     * @param Element select
     * @param string placeholder
     */
    labelEmptyOption: function(select, placeholder)
    {
        if (!placeholder) {
            return;
        }
        
        $A(select.options).each(function(option) {
            if ((option.value === '') && (option.text.strip() === '')) {
                option.text = placeholder;
            }
        });
    },
    
    /**
     * Turn the given select element into a searchable dropdown, if it is eligible
     * 
     * @param Element select
     * @param object config Accepts a "placeholder" for the empty option, and
     *                      "allowAdd" to offer creating a missing option
     * @return bool Whether the widget was created
     */
    apply: function(select, config)
    {
        if (!blcg.SearchableSelect.isEnabled()
            || !(select = $(select))
            || !this.isEligible(select)) {
            return false;
        }
        
        config = config || {};
        this.labelEmptyOption(select, config.placeholder);
        
        var box = this.build(select, config);
        select.store(this.STORAGE_KEY, box);
        
        return true;
    },
    
    /**
     * Turn every eligible select inside the given container into a searchable dropdown
     * 
     * @param Element container
     * @param object config
     * @return int Number of widgets created
     */
    applyToContainer: function(container, config)
    {
        var count = 0;
        
        if (!(container = $(container))) {
            return count;
        }
        
        container.select('select').each(function(select) {
            if (this.apply(select, config)) {
                count++;
            }
        }.bind(this));
        
        return count;
    },
    
    /**
     * Return the product attribute this select edits, when the server has said
     * that new options may be added to it
     * 
     * @param Element select
     * @param object config
     * @return object|null
     */
    getAddableAttribute: function(select, config)
    {
        var settings = blcg.SearchableSelect.config;
        
        if (!config.allowAdd || !settings.addOptionUrl || !settings.addOptionAttributes) {
            return null;
        }
        
        /*
         * The product form names its attribute fields product[<code>], and
         * product[<code>][] for the multiple-selection ones. Anything else on
         * the page - a recurring profile field, a website picker - is not an
         * attribute and gets no "add new".
         */
        var name = String(select.name || '').match(/^product\[([a-zA-Z0-9_]+)\](\[\])?$/);
        
        if (!name || !settings.addOptionAttributes[name[1]]) {
            return null;
        }
        
        return { code: name[1], id: settings.addOptionAttributes[name[1]] };
    },
    
    /**
     * @param Element select
     * @param object config
     * @return Element The widget's container
     */
    build: function(select, config)
    {
        var addable = this.getAddableAttribute(select, config);
        var box = new Element('div', {
            'class': 'blcg-select'
                + (select.multiple ? ' blcg-select-multiple' : '')
                + (config.formContext ? ' blcg-select-form' : '')
        });
        
        box.update(
            '<div class="blcg-select-control"><span class="blcg-select-summary"></span>'
            + '<i class="blcg-select-arrow"></i></div>'
            + '<div class="blcg-select-panel">'
            + '<div class="blcg-select-search"><input type="text" autocomplete="off" placeholder="'
            + this.escape(this.translate('Search...')) + '" />'
            + (addable
                ? '<a href="#" class="blcg-select-add" style="display:none">'
                  + this.escape(this.translate('Add new')) + '</a>'
                : '')
            + '</div>'
            + (select.multiple
                ? '<div class="blcg-select-actions"><a href="#" class="blcg-select-none">'
                  + this.escape(this.translate('Clear')) + '</a></div>'
                : '')
            + '<ul class="blcg-select-options">' + this.buildRows(select) + '</ul>'
            + '</div>'
        );
        
        select.insert({ after: box });
        
        /*
         * Prototype's Validation.isVisible() walks up from a field and treats a
         * display:none one as absent, skipping every rule on it - so hiding the
         * select outright would silently switch off required-entry validation
         * for any field replaced here. This class keeps it in the layout at one
         * transparent pixel instead: invisible, but still validated, and still
         * where the validator anchors its advice. See styles.css.
         */
        select.addClassName('blcg-select-source');
        
        if (addable) {
            box.store('blcg.addable', addable);
        }
        
        this.bind(select, box);
        this.refresh(select, box);
        this.listen();
        
        return box;
    },
    
    /**
     * @param Element select
     * @return string
     */
    buildRows: function(select)
    {
        var rows = '';
        
        $A(select.options).each(function(option, index) {
            var label = '<span>' + this.escape(option.text) + '</span>';
            
            rows += '<li class="blcg-select-option" data-index="' + index + '">'
                 + (select.multiple
                    ? '<label><input type="checkbox" data-index="' + index + '"'
                      + (option.selected ? ' checked' : '') + ' /> ' + label + '</label>'
                    : label)
                 + '</li>';
        }.bind(this));
        
        return rows;
    },
    
    bind: function(select, box)
    {
        var handler = this;
        var panel = box.down('.blcg-select-panel');
        var search = box.down('.blcg-select-search input');
        var options = box.down('.blcg-select-options');
        
        box.down('.blcg-select-control').observe('click', function(event) {
            event.stop();
            handler.toggle(box);
        });
        
        if (select.multiple) {
            options.observe('change', function(event) {
                var checkbox = Event.element(event);
                
                if (checkbox.type === 'checkbox') {
                    handler.setSelected(select, parseInt(checkbox.readAttribute('data-index'), 10), checkbox.checked);
                    handler.refresh(select, box);
                    handler.notify(select);
                }
            });
            
            box.down('.blcg-select-none').observe('click', function(event) {
                event.stop();
                $A(select.options).each(function(option) { option.selected = false; });
                handler.refresh(select, box);
                handler.notify(select);
            });
        } else {
            options.observe('click', function(event) {
                var row = Event.findElement(event, 'li');
                
                if (!row || !row.hasClassName('blcg-select-option')) {
                    return;
                }
                
                event.stop();
                handler.choose(select, box, parseInt(row.readAttribute('data-index'), 10));
            });
        }
        
        search.observe('keyup', function(event) {
            if (event.keyCode === Event.KEY_RETURN) {
                return;
            }
            
            handler.filter(select, box, search.value);
        });
        
        /*
         * Enter is what a keyboard reaches for after typing a search term: take
         * the only match when the term has narrowed the list to one, and offer
         * to create the term when it has narrowed it to none.
         */
        search.observe('keydown', function(event) {
            if (event.keyCode !== Event.KEY_RETURN) {
                return;
            }
            
            // Otherwise this submits the form the field sits in
            event.stop();
            
            var visible = handler.getVisibleRows(box);
            
            if (visible.length === 1) {
                handler.pick(select, box, visible[0]);
            } else if (!visible.length) {
                handler.add(select, box, search.value);
            }
        });
        
        var add = box.down('.blcg-select-add');
        
        if (add) {
            add.observe('click', function(event) {
                event.stop();
                handler.add(select, box, search.value);
            });
        }
        
        // Clicking inside the panel must not fold it away
        panel.observe('click', function(event) { event.stopPropagation(); });
    },
    
    /**
     * Bind the handlers shared by every widget on the page.
     *
     * One pair for the document, rather than one per widget: a product form
     * carries dozens of these, and each would otherwise leave its own listener
     * behind when an AJAX tab replaced its fields.
     */
    listen: function()
    {
        if (this.isListening) {
            return;
        }
        
        this.isListening = true;
        var handler = this;
        
        document.observe('click', function(event) {
            var element = Event.element(event);
            var current = (element && element.up) ? element.up('.blcg-select') : null;
            
            $$('.blcg-select-open').each(function(box) {
                if (box !== current) {
                    handler.close(box);
                }
            });
        });
        
        document.observe('keydown', function(event) {
            if (event.keyCode === Event.KEY_ESC) {
                $$('.blcg-select-open').each(function(box) { handler.close(box); });
            }
        });
    },
    
    /**
     * @param Element select
     * @param int index
     * @param bool selected
     */
    setSelected: function(select, index, selected)
    {
        if (select.options[index]) {
            select.options[index].selected = selected;
        }
    },
    
    /**
     * Take the option a row stands for
     * 
     * @param Element select
     * @param Element box
     * @param Element row
     */
    pick: function(select, box, row)
    {
        var index = parseInt(row.readAttribute('data-index'), 10);
        
        if (select.multiple) {
            var checkbox = row.down('input');
            checkbox.checked = !checkbox.checked;
            this.setSelected(select, index, checkbox.checked);
            this.refresh(select, box);
            this.notify(select);
        } else {
            this.choose(select, box, index);
        }
    },
    
    /**
     * @param Element select
     * @param Element box
     * @param int index
     */
    choose: function(select, box, index)
    {
        select.selectedIndex = index;
        this.refresh(select, box);
        this.notify(select);
        this.close(box);
    },
    
    /**
     * Tell everything bound to the field that its value moved. The native
     * select is the value, so a dependent field or a validator has to hear
     * about this exactly as it would from the native control.
     * 
     * @param Element select
     */
    notify: function(select)
    {
        // An inline onchange attribute is a listener like any other, so this
        // reaches Magento's own form fields too
        select.dispatchEvent(new Event('change', { bubbles: true }));
    },
    
    /**
     * @param Element box
     * @return Element[] The option rows the current search term leaves showing
     */
    getVisibleRows: function(box)
    {
        return box.select('.blcg-select-option').filter(function(row) {
            return row.style.display !== 'none';
        });
    },
    
    /**
     * @param Element select
     * @param Element box
     * @param string term
     */
    filter: function(select, box, term)
    {
        term = term.toLowerCase().strip();
        
        box.select('.blcg-select-option').each(function(row) {
            var matches = (term === '') || (row.down('span').innerHTML.toLowerCase().indexOf(term) !== -1);
            row.style.display = matches ? '' : 'none';
        });
        
        var add = box.down('.blcg-select-add');
        
        if (add) {
            // Only worth offering once the term has ruled every option out
            add.style.display = ((term !== '') && !this.getVisibleRows(box).length) ? '' : 'none';
        }
    },
    
    /**
     * Restate in the closed control what the native select currently holds
     * 
     * @param Element select
     * @param Element box
     */
    refresh: function(select, box)
    {
        var names = [];
        var rows = box.select('.blcg-select-option');
        
        $A(select.options).each(function(option, index) {
            if (option.selected) {
                names.push(option.text);
            }
            
            // The rows are built from the options and appended in the same
            // order, so they line up by position
            if (rows[index]) {
                if (option.selected) {
                    rows[index].addClassName('blcg-select-is-selected');
                } else {
                    rows[index].removeClassName('blcg-select-is-selected');
                }
            }
        });
        
        var summary = box.down('.blcg-select-summary');
        
        /*
         * A multiple holds nothing when nothing is ticked. A single one always
         * has a selected option, so what counts is whether that option carries
         * a value - the "-- Please Select --" row is a prompt, not a choice.
         */
        var empty = select.multiple ? !names.length : blcg.Tools.isEmptyValue(select.value);
        
        if (!names.length) {
            summary.update(this.escape(this.translate('None selected')));
        } else if (names.length <= this.MAX_NAMED) {
            summary.update(this.escape(names.join(', ')));
        } else {
            summary.update(
                this.escape(names.slice(0, this.MAX_NAMED).join(', '))
                + ' ' + this.escape(this.translate('and %s more').replace('%s', names.length - this.MAX_NAMED))
            );
        }
        
        if (empty) {
            box.addClassName('blcg-select-is-empty');
        } else {
            box.removeClassName('blcg-select-is-empty');
        }
    },
    
    /**
     * Create the typed term as a new option of the attribute this select edits.
     *
     * The option has to exist server-side before it is any use: the form posts
     * option IDs, so an option invented in the browser would be submitted as a
     * label where an ID is expected and dropped on save. The controller creates
     * it and hands back its ID, which is what goes into the select.
     * 
     * @param Element select
     * @param Element box
     * @param string label
     */
    add: function(select, box, label)
    {
        var addable = box.retrieve('blcg.addable', null);
        var link = box.down('.blcg-select-add');
        
        if (!addable || !link || (label = label.strip()) === '' || box.retrieve('blcg.adding', false)) {
            return;
        }
        
        var handler = this;
        box.store('blcg.adding', true);
        link.update(this.escape(this.translate('Adding...')));
        
        new Ajax.Request(blcg.SearchableSelect.config.addOptionUrl, {
            method: 'post',
            parameters: {
                form_key: blcg.SearchableSelect.config.formKey,
                attribute_code: addable.code,
                label: label
            },
            onComplete: function(response) {
                box.store('blcg.adding', false);
                link.update(handler.escape(handler.translate('Add new')));
                
                var result = null;
                
                try {
                    result = response.responseJSON || response.responseText.evalJSON();
                } catch (e) {}
                
                if (!result || !result.option_id) {
                    alert(result && result.message ? result.message : handler.translate('The option could not be added'));
                    return;
                }
                
                handler.insertOption(select, box, result.option_id, result.label);
            }
        });
    },
    
    /**
     * Put a freshly created option into the native select and into the panel,
     * and take it
     * 
     * @param Element select
     * @param Element box
     * @param string value
     * @param string label
     */
    insertOption: function(select, box, value, label)
    {
        var option = new Option(label, value);
        select.appendChild(option);
        
        var index = select.options.length - 1;
        var row = new Element('li', { 'class': 'blcg-select-option' });
        
        row.writeAttribute('data-index', index);
        row.update(
            select.multiple
                ? '<label><input type="checkbox" data-index="' + index + '" /> <span>'
                  + this.escape(label) + '</span></label>'
                : '<span>' + this.escape(label) + '</span>'
        );
        
        box.down('.blcg-select-options').appendChild(row);
        
        var search = box.down('.blcg-select-search input');
        search.value = '';
        this.filter(select, box, '');
        
        this.pick(select, box, row);
        
        if (select.multiple) {
            search.focus();
        }
    },
    
    toggle: function(box)
    {
        if (box.hasClassName('blcg-select-open')) {
            this.close(box);
        } else {
            this.open(box);
        }
    },
    
    open: function(box)
    {
        $$('.blcg-select-open').each(function(other) {
            if (other !== box) {
                other.removeClassName('blcg-select-open');
            }
        });
        
        box.addClassName('blcg-select-open');
        box.down('.blcg-select-search input').focus();
        this.scrollToSelection(box);
    },
    
    /**
     * Bring the current value into view. A publisher list runs to hundreds of
     * rows, and a panel that opens at the top of it does not show which one is
     * the current choice.
     * 
     * @param Element box
     */
    scrollToSelection: function(box)
    {
        var selected = box.down('.blcg-select-is-selected');
        
        if (!selected) {
            return;
        }
        
        var list = box.down('.blcg-select-options');
        
        // Measured rather than taken from offsetTop, which is relative to
        // whichever ancestor happens to be positioned
        list.scrollTop += selected.getBoundingClientRect().top
            - list.getBoundingClientRect().top
            - ((list.clientHeight - selected.offsetHeight) / 2);
    },
    
    close: function(box)
    {
        box.removeClassName('blcg-select-open');
    }
};

/**
 * Bootstrap for the admin forms named in
 * "Custom Grids > General > Searchable Dropdowns In Admin Forms".
 *
 * The grid filters and the grid editor apply the widget themselves, from
 * config.js, as part of building their rows.
 */
blcg.SelectWidget.AdminForms = {
    isInitialized: false,
    observed: [],
    
    init: function()
    {
        if (this.isInitialized || !blcg.SearchableSelect.isEnabled()) {
            return;
        }
        
        this.selectors = blcg.SearchableSelect.config.formSelectors || [];
        
        if (!this.selectors.length) {
            return;
        }
        
        this.isInitialized = true;
        this.applyToDocument();
    },
    
    /**
     * @return Element[]
     */
    getForms: function()
    {
        var forms = [];
        
        this.selectors.each(function(selector) {
            try {
                $$(selector).each(function(form) {
                    if (forms.indexOf(form) === -1) {
                        forms.push(form);
                    }
                });
            } catch (e) {
                // A selector typed into the config field can be invalid
            }
        });
        
        return forms;
    },
    
    getConfig: function()
    {
        return {
            placeholder: blcg.SelectWidget.translate('-- Please Select --'),
            formContext: true,
            allowAdd: true
        };
    },
    
    applyToDocument: function()
    {
        this.getForms().each(function(form) {
            blcg.SelectWidget.applyToContainer(form, this.getConfig());
            this.observe(form);
        }.bind(this));
    },
    
    /**
     * Most of an admin form's tabs are fetched over AJAX, and their fields
     * therefore appear well after dom:loaded. Watching the form covers those,
     * and anything else that adds fields later, without needing a hook into
     * each individual screen.
     * 
     * @param Element form
     */
    observe: function(form)
    {
        if (typeof(MutationObserver) == 'undefined' || this.observed.indexOf(form) !== -1) {
            return;
        }
        
        this.observed.push(form);
        var handler = this;
        var scheduled = false;
        
        new MutationObserver(function() {
            if (scheduled) {
                return;
            }
            
            scheduled = true;
            
            // Coalesce the burst of mutations a tab load produces into one pass
            setTimeout(function() {
                scheduled = false;
                
                try {
                    blcg.SelectWidget.applyToContainer(form, handler.getConfig());
                } catch (e) {}
            }, 200);
        }).observe(form, { childList: true, subtree: true });
    }
};

document.observe('dom:loaded', function() {
    blcg.SelectWidget.AdminForms.init();
});
