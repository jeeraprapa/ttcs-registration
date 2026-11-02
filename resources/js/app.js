import './check-in-scanner.js';
import 'flag-icons/css/flag-icons.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
const select=document.querySelector('#event_session_id'), date=document.querySelector('#selected_date');
document.querySelectorAll('[data-field-error]').forEach(error=>{const name=error.dataset.fieldError.replace(/^consents\.(.+)$/,'consents[$1]');const input=document.getElementsByName(name)[0];const target=input?.closest('.field,.consent');if(target){error.hidden=false;error.id='error-'+name.replace(/[^a-z0-9]/gi,'-');target.append(error);input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby',error.id);}});
if (select && date) {
    const choices = [...select.options].slice(1).map(option => option.cloneNode(true));
    const timeOptions = document.querySelector('[data-time-options]');
    const selection = document.querySelector('[data-session-selection]');
    const timeError = document.querySelector('#time-session-error');
    const hasValidTime = () => Boolean(select.value && !select.selectedOptions[0]?.disabled && select.selectedOptions[0]?.dataset.date === date.value);
    const showTimeError = (focus = true) => {
        if (timeError) timeError.hidden = false;
        select.setAttribute('aria-invalid', 'true');
        timeOptions?.setAttribute('aria-invalid', 'true');
        if (focus) {
            timeOptions?.scrollIntoView({ block: 'center', behavior: 'auto' });
            timeOptions?.querySelector('button:not(:disabled)')?.focus({ preventScroll: true });
        }
    };
    const clearTimeError = () => {
        if (timeError) timeError.hidden = true;
        select.removeAttribute('aria-invalid');
        timeOptions?.removeAttribute('aria-invalid');
    };
    const dateButtons = [...document.querySelectorAll('[data-session-date]')];
    const renderTimes = () => {
        if (!timeOptions) return;
        timeOptions.replaceChildren();
        [...select.options].slice(1).forEach(option => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'session-time-button';
            button.disabled = option.disabled;
            button.setAttribute('aria-pressed', String(option.value === select.value));
            const time = document.createElement('span');
            time.textContent = option.dataset.timeLabel;
            const status = document.createElement('small');
            status.textContent = option.disabled ? (option.textContent.includes('Booked') ? 'Booked' : 'Closed') : option.value === select.value ? 'Selected ✓' : 'Available';
            button.append(time, status);
            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                const next = timeOptions.querySelector(`[data-time-id="${option.value}"]`);
                next?.focus({ preventScroll: true });
            });
            button.dataset.timeId = option.value;
            timeOptions.append(button);
        });
        if (selection) {
            const day = dateButtons.find(button => button.dataset.sessionDate === date.value)?.dataset.dateLabel || '';
            const time = select.selectedOptions[0]?.dataset.timeLabel;
            selection.textContent = time ? `${day} · ${time} (Hong Kong time)` : `${day} · Choose a time above`;
            selection.classList.toggle('has-selection', Boolean(time));
        }
    };
    const update = (value, reset) => {
        const previous = reset ? '' : select.value;
        select.replaceChildren(new Option('Choose an option', ''));
        choices.filter(option => option.dataset.date === value).forEach(option => select.add(option.cloneNode(true)));
        select.value = previous;
        if (!select.value || select.selectedOptions[0]?.disabled) select.value = '';
        date.value = value;
        dateButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.sessionDate === value)));
        renderTimes();
    };
    dateButtons.forEach(button => button.addEventListener('click', () => update(button.dataset.sessionDate, true)));
    select.addEventListener('change', () => {
        if (hasValidTime()) clearTimeError();
        renderTimes();
    });
    const requireTimeSession = event => {
        if (!hasValidTime()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showTimeError();
        }
    };
    select.form?.addEventListener('submit', requireTimeSession, { capture: true });
    select.form?.querySelector('[type=submit]')?.addEventListener('click', requireTimeSession);
    if (timeOptions) {
        timeOptions.hidden = false;
        select.classList.add('session-native-select');
        select.tabIndex = -1;
        select.addEventListener('invalid', event => {
            event.preventDefault();
            showTimeError();
        });
    }
    update(date.value, false);
    if (select.dataset.availabilityUrl) {
        const refreshAvailability = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(select.dataset.availabilityUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const statuses = new Map((await response.json()).map(slot => [String(slot.id), slot]));
                choices.forEach(option => {
                    const status = statuses.get(option.value);
                    option.dataset.label ||= option.textContent.replace(/ — (Booked|Closed)$/, '');
                    option.disabled = !status?.available;
                    option.textContent = option.dataset.label + (status?.available ? '' : ` — ${status?.status || 'Closed'}`);
                });
                const previous = select.value;
                const focusedId = timeOptions?.contains(document.activeElement) ? document.activeElement.dataset.timeId : null;
                update(date.value, false);
                if (focusedId) timeOptions.querySelector(`[data-time-id="${focusedId}"]:not(:disabled)`)?.focus({ preventScroll: true });
                if (previous && !select.value) {
                    showTimeError(false);
                    document.querySelector('.availability').textContent = 'Your selected session is no longer available. Please select another session.';
                }
            } catch { /* Availability is checked again on the server when submitting. */ }
        };
        setInterval(refreshAvailability, 15000);
        window.addEventListener('focus', refreshAvailability);
    }
}
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

const sessionFilterMonth = document.querySelector('[data-session-filter-month]');
const sessionFilterDay = document.querySelector('[data-session-filter-day]');
if (sessionFilterMonth && sessionFilterDay) {
    const dayChoices = [...sessionFilterDay.options].slice(1).map(option => option.cloneNode(true));
    const updateFilterDays = () => {
        const previous = sessionFilterDay.value;
        sessionFilterDay.replaceChildren(new Option('All days', ''));
        dayChoices.filter(option => !sessionFilterMonth.value || option.dataset.month === sessionFilterMonth.value)
            .forEach(option => sessionFilterDay.add(option.cloneNode(true)));
        sessionFilterDay.value = previous;
        if (!sessionFilterDay.value) sessionFilterDay.value = '';
    };
    sessionFilterMonth.addEventListener('change', updateFilterDays);
    updateFilterDays();
}
