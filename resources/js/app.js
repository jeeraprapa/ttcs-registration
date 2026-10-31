import 'flag-icons/css/flag-icons.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
const select=document.querySelector('#event_session_id'), date=document.querySelector('#selected_date');
document.querySelectorAll('[data-field-error]').forEach(error=>{const name=error.dataset.fieldError.replace(/^consents\.(.+)$/,'consents[$1]');const input=document.getElementsByName(name)[0];const target=input?.closest('.field,.consent');if(target){error.hidden=false;error.id='error-'+name.replace(/[^a-z0-9]/gi,'-');target.append(error);input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby',error.id);}});
if(select&&date){const choices=[...select.options].slice(1).map(o=>o.cloneNode(true));const update=(value,reset)=>{const previous=reset?'':select.value;select.replaceChildren(new Option('Choose an option',''));choices.filter(o=>o.dataset.date===value).forEach(o=>select.add(o.cloneNode(true)));select.value=previous;date.value=value;document.querySelectorAll('[data-session-date]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.sessionDate===value)));};document.querySelectorAll('[data-session-date]').forEach(b=>b.addEventListener('click',()=>update(b.dataset.sessionDate,true)));update(date.value,false);}
const country=document.querySelector('#phone_country_code'),dial=document.querySelector('#phone_dial_code');if(country&&dial){const update=()=>dial.value=country.selectedOptions[0]?.dataset.dialCode||'';country.addEventListener('change',update);update();}
if (country) {
    const picker = document.createElement('div');
    picker.className = 'country-picker';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'country-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-controls', 'phone-country-options');
    const list = document.createElement('div');
    list.id = 'phone-country-options';
    list.className = 'country-options';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Country calling code');
    list.hidden = true;
    const options = [...country.options];
    const buttons = options.map(option => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'country-option';
        button.setAttribute('role', 'option');
        button.tabIndex = -1;
        const flag = document.createElement('span');
        flag.className = `fi fi-${option.value.toLowerCase()}`;
        flag.setAttribute('aria-hidden', 'true');
        button.append(flag, document.createTextNode(`${option.dataset.countryName} ${option.dataset.dialCode}`));
        button.addEventListener('click', () => {
            country.value = option.value;
            country.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        });
        list.append(button);
        return button;
    });
    const sync = () => {
        const option = country.selectedOptions[0];
        if (!option) return;
        const flag = document.createElement('span');
        flag.className = `fi fi-${option.value.toLowerCase()}`;
        flag.setAttribute('aria-hidden', 'true');
        trigger.replaceChildren(flag, document.createTextNode(option.dataset.dialCode), document.createTextNode(' ▾'));
        trigger.setAttribute('aria-label', `Country calling code: ${option.dataset.countryName} ${option.dataset.dialCode}`);
        buttons.forEach((button, i) => button.setAttribute('aria-selected', String(options[i].selected)));
    };
    const close = (focus = true) => {
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (focus) trigger.focus();
    };
    const open = () => {
        list.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        buttons[country.selectedIndex]?.focus();
    };
    trigger.addEventListener('click', () => list.hidden ? open() : close());
    trigger.addEventListener('keydown', event => {
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(); }
    });
    list.addEventListener('keydown', event => {
        const index = buttons.indexOf(document.activeElement);
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1
                : (index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length;
            buttons[next].focus();
        }
        if (event.key === 'Tab') close();
    });
    document.addEventListener('click', event => { if (!picker.contains(event.target)) close(false); });
    picker.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) close(false); });
    country.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);
    country.after(picker);
    picker.append(trigger, list);
    country.hidden = true;
    if (country.getAttribute('aria-invalid')) {
        trigger.setAttribute('aria-invalid', 'true');
        trigger.setAttribute('aria-describedby', country.getAttribute('aria-describedby'));
    }
    trigger.setAttribute('aria-expanded', 'false');
    sync();
}
document.querySelectorAll('form[data-submit-once]').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('[type=submit]');button.disabled=true;button.dataset.originalText=button.textContent;button.textContent=button.dataset.loadingText||'Please wait…';}));window.addEventListener('pageshow',()=>document.querySelectorAll('button[data-original-text]').forEach(b=>{b.disabled=false;b.textContent=b.dataset.originalText;}));
