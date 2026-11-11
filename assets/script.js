const darkBtn = document.getElementById('darkModeBtn');

if(localStorage.getItem('darkmode') == 'active'){
    document.body.classList.add('dark-mode');
}

if(darkBtn){

    darkBtn.onclick = function(){

        document.body.classList.toggle('dark-mode');

        if(document.body.classList.contains('dark-mode')){
            localStorage.setItem('darkmode','active');
        }else{
            localStorage.removeItem('darkmode');
        }
    }
}

// CHART
const ctx = document.getElementById('chartBuku');

if(ctx){
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['IPA','IPS','Teknologi','Novel','Agama'],
        datasets: [{
            label: 'Jumlah Buku',
            data: [12,19,8,15,10]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio:false
    }
});
}

// AJAX REALTIME
function loadStatistik(){

    fetch('get_statistik.php')
    .then(res => res.json())
    .then(data => {

        document.getElementById('totalBuku').innerHTML = data.buku;
        document.getElementById('totalSiswa').innerHTML = data.siswa;
        document.getElementById('totalKriteria').innerHTML = data.kriteria;
        document.getElementById('totalRanking').innerHTML = data.ranking;

    })
}

setInterval(loadStatistik,3000);
loadStatistik();

// NOTIFIKASI
function showNotif(text){

    const notif = document.createElement('div');

    notif.className = 'alert alert-success position-fixed top-0 end-0 m-4 shadow';

    notif.style.zIndex = '9999';

    notif.innerHTML = text;

    document.body.appendChild(notif);

    setTimeout(()=>{
        notif.remove();
    },3000);
}
