<?php
// includes/footer.php
?>
            </div> <!-- /.content-wrapper -->
            
            <!-- Footer -->
            <footer class="main-footer">
                <div class="footer-content">
                    <div class="footer-left">
                        <p>&copy; <?php echo date('Y'); ?> Hotel Management System. All rights reserved.</p>
                    </div>
                    <div class="footer-right">
                        <p>Version 2.0 | Server Time: <?php echo date('d M Y H:i:s'); ?></p>
                    </div>
                </div>
            </footer>
        </main>
    </div>
    
    <!-- JavaScript -->
    <script src="../assets/js/admin.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            // Initialize all tables with DataTables
            $('.data-table').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Copy',
                        className: 'btn-copy'
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn-csv',
                        title: '<?php echo $page_title; ?> - <?php echo date("Y-m-d"); ?>'
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn-excel',
                        title: '<?php echo $page_title; ?> - <?php echo date("Y-m-d"); ?>'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn-pdf',
                        title: '<?php echo $page_title; ?> - <?php echo date("Y-m-d"); ?>'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn-print'
                    },
                    'colvis'
                ],
                responsive: true,
                pageLength: 25,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
            
            // Menu toggle
            $('#menuToggle').click(function() {
                $('.sidebar').toggleClass('collapsed');
                $('.main-content').toggleClass('expanded');
            });
            
            // Submenu toggle
            $('.has-submenu > .nav-link').click(function(e) {
                e.preventDefault();
                $(this).parent().toggleClass('open');
                $(this).find('.fa-chevron-right').toggleClass('rotated');
            });
            
            // Notification toggle
            $('.btn-notification').click(function() {
                alert('Notifications feature coming soon!');
            });
        });
        
        // Chart initialization function
        function initChart(chartId, type, data, options = {}) {
            const ctx = document.getElementById(chartId);
            if (!ctx) return;
            
            const defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            };
            
            const finalOptions = {...defaultOptions, ...options};
            
            new Chart(ctx, {
                type: type,
                data: data,
                options: finalOptions
            });
        }
        
        // Export functions
        function exportToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let row of rows) {
                const cells = row.querySelectorAll('th, td');
                let rowData = [];
                
                for (let cell of cells) {
                    // Skip action columns
                    if (cell.classList.contains('no-export')) continue;
                    
                    let text = cell.innerText;
                    // Handle commas in text
                    text = text.replace(/"/g, '""');
                    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                        text = '"' + text + '"';
                    }
                    rowData.push(text);
                }
                
                csv.push(rowData.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (navigator.msSaveBlob) {
                navigator.msSaveBlob(blob, filename);
            } else {
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        function exportToPDF(tableId, filename) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'pt', 'a4');
            const table = document.getElementById(tableId);
            
            doc.autoTable({
                html: table,
                theme: 'grid',
                styles: {
                    fontSize: 8,
                    cellPadding: 3
                },
                headStyles: {
                    fillColor: [52, 152, 219],
                    textColor: 255
                }
            });
            
            doc.save(filename);
        }
        
        // Print function
        function printTable(tableId) {
            const table = document.getElementById(tableId);
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Print - <?php echo $page_title; ?></title>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #3498db; color: white; }
                            @media print {
                                @page { size: landscape; }
                            }
                        </style>
                    </head>
                    <body>
                        <h2><?php echo $page_title; ?></h2>
                        <p>Printed on: ${new Date().toLocaleString()}</p>
                        ${table.outerHTML}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>