document.addEventListener('DOMContentLoaded', function () {
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    const monthNames = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
    
    const calendarContainer = document.getElementById('calendar-container');
    console.log(calendarContainer);

    if(!calendarContainer) {
        console.error('Element with id "calendar-container" not found');
        return; // arrête l'exécution du script si calendarContainer est null
    }

    const userId = calendarContainer.dataset.userId;
    
    function updateCalendar() {
        const calendarTable = document.getElementById('calendar-table');
        document.getElementById('current-month-year').textContent = monthNames[currentMonth] + ' ' + currentYear;
        
        let firstDay = new Date(currentYear, currentMonth, 1);
        let lastDay = new Date(currentYear, currentMonth + 1, 0);
        
        let html = '<tr><th>Di</th><th>Lu</th><th>Ma</th><th>Me</th><th>Je</th><th>Ve</th><th>Sa</th></tr>';
        
        let dayOfWeekFirst = firstDay.getDay();
        let dayOfWeekLast = lastDay.getDay();
        
        html += '<tr>';
        
        if(dayOfWeekFirst !== 0) {
            let prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
            for(let i = dayOfWeekFirst - 1; i >= 0; i--) {
                html += `<td class="prev-month">${prevMonthLastDay - i}</td>`;
            }
        }

        for(let day = 1; day <= lastDay.getDate(); day++) {
            if((day + dayOfWeekFirst - 1) % 7 === 0 && day !== 1) html += '</tr><tr>';
            html += `<td>${day}</td>`;
        }

        if(dayOfWeekLast !== 6) {
            for(let i = 1; i <= 6 - dayOfWeekLast; i++) {
                html += `<td class="next-month">${i}</td>`;
            }
        }
        
        html += '</tr>';
        
        calendarTable.innerHTML = html;
    }

    let calendarTable = document.getElementById('calendar-table');
    calendarTable.replaceWith(calendarTable.cloneNode(true));
    calendarTable = document.getElementById('calendar-table');
    
    calendarTable.addEventListener('click', function(e) {
        console.log('Click event triggered');
        if(e.target.tagName.toLowerCase() === 'td' && e.target.textContent !== '') {
            let day = e.target.textContent;
            showTimeSlots(day);
        }
    });
    
    function showTimeSlots(day) {
        console.log("showTimeSlots called for day", day);

        const errorMessage = document.getElementById('error-message');
        const timeslotContainer = document.getElementById('timeslot-container');
        timeslotContainer.innerHTML = '';
        const reserveButton = document.getElementById('reserve-button');
        
        if (userId == 0) { 
            errorMessage.textContent = 'Vous devez être connecté pour réserver un créneau';
            errorMessage.style.color = 'red';
            reserveButton.disabled = true; 
            return;
        } else {
            errorMessage.textContent = ''; 
            reserveButton.disabled = false; 
        }

        const selectedDate = `${currentYear}-${currentMonth + 1}-${day}`;
    
        fetch(my_script_vars.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=fetch_reserved_slots&date=${selectedDate}&nonce=${my_script_vars.nonce}`,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const reservedSlots = data.data;
                for (let i = 8; i < 19; i++) {
                    for (let j = 0; j < 2; j++) {
                        const timeslot = i + ':' + (j === 0 ? '00' : '30');
                        let timeslotDiv = document.createElement('div');
                        console.log("timeslotDiv created", timeslotDiv);
                        timeslotDiv.className = 'timeslot' + (reservedSlots.includes(timeslot) ? ' reserved' : '');
                        timeslotDiv.textContent = timeslot;
                        timeslotDiv.dataset.day = day;
    
                        timeslotDiv.addEventListener('click', function() {
                            if (!this.classList.contains('reserved')) {
                                this.classList.toggle('selected');
                                reserveButton.style.display = document.querySelectorAll('.timeslot.selected').length > 0 ? 'block' : 'none';
                            }
                        });
    
                        timeslotContainer.appendChild(timeslotDiv);
                    }
                }
            } else {
                console.error('Could not get reserved slots');
            }
        })
        .catch(error => console.error('Fetch error:', error));
    }

    document.getElementById('prev-month').addEventListener('click', function () {
        if (currentMonth === 0) {
            currentMonth = 11;
            currentYear--;
        } else {
            currentMonth--;
        }
        updateCalendar();
    });

    document.getElementById('next-month').addEventListener('click', function () {
        if (currentMonth === 11) {
            currentMonth = 0;
            currentYear++;
        } else {
            currentMonth++;
        }
        updateCalendar();
    });

    document.getElementById('reserve-button').addEventListener('click', function() {
        const selectedSlots = document.querySelectorAll('.timeslot.selected');
        
        if (selectedSlots.length === 0) return;
        
        const slotsData = [];
        selectedSlots.forEach(slot => {
            slotsData.push({
                day: slot.dataset.day,
                time: slot.textContent,
            });
        });
        
        const selectedDay = selectedSlots[0].dataset.day; 
        const selectedDate = `${currentYear}-${currentMonth + 1}-${selectedDay}`; 
        
        const formData = new FormData();
        formData.append('action', 'reserve_slots');
        formData.append('userId', userId);
        formData.append('date', selectedDate);
        formData.append('slots', JSON.stringify(slotsData));
        formData.append('nonce', my_script_vars.nonce_reserve_slots);
        
        fetch(my_script_vars.ajaxurl, { 
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                console.log('Réservation réussie');
                window.location.href = data.data.redirect_url;
            } else {
                console.error('Erreur de réservation');
            }
        })
        .catch(error => {
            console.error('Une erreur s\'est produite', error);
        });
    });    
    updateCalendar();
});