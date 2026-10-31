(() => {
    'use strict';

    const colors = ['#198754', '#2563eb', '#d97706', '#7c3aed'];
    const statusColors = ['#64748b', '#d97706', '#2563eb', '#198754'];

    function wrap(ctx, value, width) {
        const lines = [];
        for (const paragraph of String(value ?? '').split('\n')) {
            let line = '';
            for (const word of paragraph.split(/\s+/)) {
                if (line && ctx.measureText(`${line} ${word}`).width > width) {
                    lines.push(line);
                    line = '';
                }
                // Split unusually long unbroken names so exported cells never overlap.
                let chunk = '';
                for (const char of word) {
                    if (ctx.measureText(chunk + char).width > width) {
                        if (line) lines.push(line);
                        lines.push(chunk);
                        line = '';
                        chunk = '';
                    }
                    chunk += char;
                }
                line = line ? `${line} ${chunk}` : chunk;
            }
            lines.push(line);
        }
        return lines;
    }

    function message(text) {
        let target = document.getElementById('theme-export-status');
        if (!target) {
            target = document.createElement('p');
            target.id = 'theme-export-status';
            target.setAttribute('role', 'status');
            target.className = 'alert alert-info';
            document.querySelector('main').appendChild(target);
        }
        target.textContent = text;
    }

    function save(canvas, filename) {
        canvas.toBlob(blob => {
            if (!blob) return message('The image could not be generated. Try downloading the Excel table instead.');
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${filename}.png`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 10000);
        }, 'image/png');
    }

    function drawTable(data) {
        const canvas = document.createElement('canvas');
        const widths = [65, 420, 115, 95, 80, 100, 120, 90, 300, 115];
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Your browser does not support image exports.');
        ctx.font = '16px Arial';
        const rows = [data.headers, ...data.rows].map((cells, row) => {
            ctx.font = row === 0 ? 'bold 16px Arial' : '16px Arial';
            return cells.map((cell, i) => wrap(ctx, cell, widths[i] - 20));
        });
        const heights = rows.map(row => Math.max(50, ...row.map(lines => lines.length * 22 + 24)));
        canvas.width = widths.reduce((a, b) => a + b, 0) + 48;
        canvas.height = heights.reduce((a, b) => a + b, 0) + 140;
        if (canvas.height > 30000) throw new Error('This table is too large for one image. Please download Excel instead.');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#123b5d';
        ctx.font = 'bold 28px Arial';
        ctx.fillText('MicroNuT-IPHL — Theme Execution', 24, 40);
        ctx.font = '16px Arial';
        ctx.fillText(`Generated ${data.date} | Directly linked main tasks and subtasks; overdue is a subset of unfinished work.`, 24, 72);
        let y = 104;
        rows.forEach((row, r) => {
            let x = 24;
            row.forEach((lines, c) => {
                ctx.fillStyle = r === 0 ? '#198754' : (r % 2 ? '#f1f5f9' : '#ffffff');
                ctx.fillRect(x, y, widths[c], heights[r]);
                ctx.strokeStyle = '#cbd5e1';
                ctx.strokeRect(x, y, widths[c], heights[r]);
                ctx.fillStyle = r === 0 ? '#ffffff' : '#163047';
                ctx.font = r === 0 ? 'bold 16px Arial' : '16px Arial';
                lines.forEach((line, i) => ctx.fillText(line, x + 10, y + 24 + i * 22));
                x += widths[c];
            });
            y += heights[r];
        });
        return canvas;
    }

    function drawChart(chart, period) {
        const canvas = document.getElementById(chart.id);
        canvas.width = 1200;
        canvas.height = chart.type === 'bar' ? Math.max(440, chart.labels.length * 42 + 180) : 500;
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Your browser does not support charts. The chart data tables remain available.');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#123b5d';
        ctx.font = 'bold 26px Arial';
        ctx.fillText(chart.title, 24, 38);
        ctx.font = '16px Arial';
        const current = ['theme-status', 'theme-backlog'].includes(chart.id);
        ctx.fillText(current ? `Current position | Report period: ${period}` : `Reporting period: ${period}`, 24, 66);
        if (chart.type === 'donut') {
            const values = chart.datasets[0].data;
            const total = values.reduce((sum, n) => sum + n, 0);
            let angle = -Math.PI / 2;
            ctx.lineWidth = 72;
            ctx.strokeStyle = '#e2e8f0';
            ctx.beginPath(); ctx.arc(340, 285, 140, 0, Math.PI * 2); ctx.stroke();
            values.forEach((value, i) => {
                const end = angle + (total ? value / total * Math.PI * 2 : 0);
                if (value) {
                    ctx.strokeStyle = statusColors[i];
                    ctx.beginPath(); ctx.arc(340, 285, 140, angle, end); ctx.stroke();
                }
                ctx.fillStyle = statusColors[i]; ctx.fillRect(620, 175 + i * 55, 20, 20);
                ctx.fillStyle = '#163047'; ctx.font = '22px Arial';
                ctx.fillText(`${chart.labels[i]}: ${value}`, 655, 193 + i * 55);
                angle = end;
            });
            ctx.textAlign = 'center'; ctx.font = 'bold 48px Arial'; ctx.fillStyle = '#123b5d';
            ctx.fillText(total, 340, 290); ctx.font = '20px Arial'; ctx.fillText('Themes', 340, 322);
            return;
        }
        let legendX = 24;
        chart.datasets.forEach((dataset, i) => {
            ctx.fillStyle = colors[i]; ctx.fillRect(legendX, 88, 16, 16);
            ctx.fillStyle = '#163047'; ctx.font = '16px Arial'; ctx.fillText(dataset.label, legendX + 24, 102);
            legendX += ctx.measureText(dataset.label).width + 65;
        });
        const rawMax = Math.max(0, ...chart.datasets.flatMap(s => s.data).filter(v => v !== null));
        const max = Math.max(5, Math.ceil(rawMax * 1.12 / 5) * 5);
        const left = chart.type === 'bar' ? 85 : 70, right = 1140, top = 140, bottom = canvas.height - 65;
        ctx.font = '14px Arial'; ctx.lineWidth = 1;
        for (let i = 0; i <= 5; i++) {
            const value = max * i / 5;
            ctx.strokeStyle = '#e2e8f0'; ctx.fillStyle = '#52606d'; ctx.beginPath();
            if (chart.type === 'bar') {
                const x = left + (right - left) * i / 5;
                ctx.moveTo(x, top); ctx.lineTo(x, bottom);
                ctx.fillText(Number(value.toFixed(1)), x - 8, bottom + 25);
            } else {
                const y = bottom - (bottom - top) * i / 5;
                ctx.moveTo(left, y); ctx.lineTo(right, y);
                ctx.fillText(Number(value.toFixed(1)), 18, y + 5);
            }
            ctx.stroke();
        }
        if (chart.type === 'bar') {
            const groupHeight = (bottom - top) / Math.max(1, chart.labels.length);
            const barHeight = Math.min(13, (groupHeight - 8) / chart.datasets.length);
            chart.labels.forEach((label, row) => {
                const y = top + row * groupHeight;
                ctx.fillStyle = '#163047'; ctx.font = 'bold 16px Arial'; ctx.fillText(label, 18, y + groupHeight / 2 + 5);
                chart.datasets.forEach((dataset, i) => {
                    const value = dataset.data[row];
                    const barY = y + (groupHeight - barHeight * chart.datasets.length) / 2 + i * barHeight;
                    ctx.fillStyle = colors[i];
                    if (value !== null) ctx.fillRect(left, barY, value / max * (right - left), barHeight - 1);
                    ctx.font = '12px Arial'; ctx.fillStyle = '#163047';
                    ctx.fillText(value === null ? 'No data' : String(value), left + (value ?? 0) / max * (right - left) + 5, barY + barHeight - 2);
                });
            });
        } else {
            const pointX = i => left + i / Math.max(1, chart.labels.length - 1) * (right - left);
            chart.labels.forEach((label, i) => {
                if (i % Math.max(1, Math.ceil(chart.labels.length / 9)) === 0 || i === chart.labels.length - 1) {
                    ctx.save(); ctx.translate(pointX(i), bottom + 20); ctx.rotate(-0.35);
                    ctx.fillStyle = '#52606d'; ctx.fillText(label, -20, 0); ctx.restore();
                }
            });
            chart.datasets.forEach((dataset, series) => {
                ctx.strokeStyle = colors[series]; ctx.lineWidth = 3; ctx.beginPath();
                dataset.data.forEach((value, i) => {
                    const x = pointX(i), y = bottom - value / max * (bottom - top);
                    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                });
                ctx.stroke();
                dataset.data.forEach((value, i) => {
                    ctx.beginPath(); ctx.fillStyle = colors[series];
                    ctx.arc(pointX(i), bottom - value / max * (bottom - top), 3, 0, Math.PI * 2); ctx.fill();
                });
            });
        }
        if (rawMax === 0) {
            ctx.fillStyle = '#52606d'; ctx.font = '18px Arial';
            ctx.fillText('No matching activity. See chart data below.', left + 90, top + 28);
        }
    }

    const tableData = document.getElementById('theme-table-data');
    if (tableData) {
        document.getElementById('download-themes-image').addEventListener('click', () => {
            try { save(drawTable(JSON.parse(tableData.textContent)), 'nutrition-themes'); }
            catch (error) { message(error.message); }
        });
    }
    const chartData = document.getElementById('theme-chart-data');
    if (chartData) {
        const data = JSON.parse(chartData.textContent);
        for (const chart of data.charts) {
            try { drawChart(chart, data.period); }
            catch (error) { message(error.message); }
        }
        document.querySelectorAll('[data-download-chart]').forEach(button => {
            button.addEventListener('click', () => {
                try { save(document.getElementById(button.dataset.downloadChart), `${button.dataset.downloadChart}-${data.period.replace(/[^0-9a-z-]/g, '-')}`); }
                catch (error) { message(error.message); }
            });
        });
    }
})();
