window.onload = function () {
    var ctx = document.getElementById('canvas').getContext('2d');
    window.myLine = Chart.Line(ctx, {
        data: lineChartData,
        options: {
            responsive: true,
            hoverMode: 'index',
            stacked: false,
            scales: {
                yAxes: [{
                    type: 'linear',
                    display: true,
                    position: 'left',
                    id: 'y-axis-1'
                }, {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    id: 'y-axis-2',
                    gridLines: {
                        drawOnChartArea: false
                    }
                }]
            }
        }
    });
};

var lineChartData = {
    labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
    datasets: [{
        label: 'Abandoned Amount',
        borderColor: 'rgb(255, 99, 132)',
        backgroundColor: 'rgb(255, 99, 132)',
        fill: false,
        data: [
            10,
            23,
            12,
            45,
            78,
            45,
            6
        ],
        yAxisID: 'y-axis-1'
    }, {
        label: 'Recovered Amount',
        borderColor: '#01a300',
        backgroundColor: '#01a300',
        fill: false,
        data: [
            7,
            55,
            75,
            78,
            90,
            89,
            100
        ],
        yAxisID: 'y-axis-2'
    }]
};