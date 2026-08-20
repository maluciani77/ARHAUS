const deg = 6;
const hr = document.querySelector("#hr");
const mn = document.querySelector("#mn");
const sc = document.querySelector("#sc");

setInterval(() => {
    let day = new Date();
    let hh = day.getHours() ;
    let mm = day.getMinutes();
    let ss = day.getSeconds();
    hr.style.transform = `rotateZ(${hh+(mm/12)}
    deg)`;
    mn.style.transform = `rotateZ(${mm}deg)`;
    sc.style.transform = `rotateZ(${ss}deg)`;
});