# Cấu Trúc Báo Cáo Giữa Kỳ (Midterm Report Outline)

**Đề tài:** Bảng Điều Khiển Web Tương Tác Phục Vụ Khai Phá Tập Mục Phổ Biến và Phân Tích Cắt Tỉa Apriori  
*(Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis)*  
**Môn học:** Ứng Dụng và Lập Trình Web (Web Programming and Applications)  
**Mã nguồn & Bằng chứng thực nghiệm:** `D:\Projects\fim-dashboard`  
**Nhánh thực nghiệm:** `phase/4-experiments`  

---

## 1. Giới Thiệu (Introduction)
- Bối cảnh bài toán khai phá dữ liệu web và phân tích hành vi người dùng trong các hệ thống thương mại / giao dịch.
- Tầm quan trọng của việc trực quan hóa tương tác kết quả khai phá tập mục phổ biến và luật kết hợp đối với người dùng cuối và nhà phân tích.
- Đóng góp chính của đề tài: Xây dựng hệ thống bảng điều khiển web tương tác hoàn chỉnh (PHP/MySQL/AJAX/ECharts) tích hợp thuật toán Apriori, cơ chế cắt tỉa kiểm soát và phân tích hiệu năng front-end đối chứng.

## 2. Phát Biểu Bài Toán (Problem Statement)
- Thách thức về không gian tìm kiếm tổ hợp bùng nổ khi hạ ngưỡng hỗ trợ tối thiểu trong thuật toán Apriori.
- Khoảng cách giữa các công cụ khai phá dữ liệu dòng lệnh (CLI) trừu tượng và nhu cầu trực quan hóa web tương tác trong thời gian thực.
- Sự đánh đổi về hiệu năng hiển thị và cập nhật dữ liệu điểm phân tán dày đặc trên các thư viện trực quan hóa web front-end khác nhau.

## 3. Mục Tiêu và Câu Hỏi Nghiên Cứu (Objectives and Research Questions)
- **Mục tiêu dự án:** Hiện thực hóa kiến trúc web MVC/dịch vụ hoàn chỉnh, xây dựng động cơ Apriori tối ưu và cung cấp giao diện trực quan hóa tương tác.
- **RQ1:** Ngưỡng hỗ trợ tối thiểu ($\text{min\_support}$) ảnh hưởng như thế nào đến khối lượng ứng viên sinh ra, số lượng tập mẫu phổ biến / luật kết hợp và thời gian thực thi của thuật toán Apriori?
- **RQ2:** Cơ chế cắt tỉa tập con theo tính chất Apriori (Apriori-property subset pruning) đạt hiệu quả ra sao trong việc thu hẹp không gian tìm kiếm ứng viên qua từng bậc độ dài tập mục $k$?
- **RQ3:** Ba thư viện trực quan hóa web D3.js (SVG), Chart.js (Canvas) và Apache ECharts (Canvas) so sánh như thế nào về độ trễ khởi tạo (render) và cập nhật dữ liệu tại chỗ (in-place update) dưới các khối lượng dữ liệu điểm phân tán tương đương?

## 4. Cơ Sở Lý Thuyết (Theoretical Background)
- **4.1 Khai phá tập mục phổ biến (Frequent Itemset Mining - FIM):** Định nghĩa tập mục, giao dịch và không gian mạng lưới tập con (itemset lattice).
- **4.2 Độ hỗ trợ (Support):** Khái niệm, công thức toán học và ngưỡng $\text{min\_support}$.
- **4.3 Độ tin cậy (Confidence):** Khái niệm, công thức toán học cho luật kết hợp $X \Rightarrow Y$ và ngưỡng $\text{min\_confidence}$.
- **4.4 Độ nâng (Lift):** Định nghĩa, công thức đo lường mức độ tương quan độc lập giữa tiền đề và hệ quả.
- **4.5 Tính chất Apriori (Apriori Property / Anti-monotonicity):** Định lý tính chất đơn điệu giảm của độ hỗ trợ.
- **4.6 Cơ chế sinh ứng viên và cắt tỉa (Candidate Generation & Pruning):** Thủ tục kết nối $L_{k-1} \Join L_{k-1}$ và thủ tục loại bỏ ứng viên có tập con không phổ biến.
- **4.7 Thuật toán FP-Growth (Frequent Pattern Growth):** Giới thiệu lý thuyết cấu trúc cây FP-tree nén và khai phá mẫu tăng trưởng như giải pháp thay thế lý thuyết không sinh ứng viên.
- **4.8 Các thư viện trực quan hóa dữ liệu web:** So sánh nền tảng kiến trúc DOM/SVG (D3.js) và HTML5 Canvas rasterization (Chart.js, Apache ECharts).

## 5. Yêu Cầu Hệ Thống và Công Nghệ (System Requirements and Technology Stack)
- Yêu cầu chức năng: Quản lý tập dữ liệu, cấu hình tham số khai phá, hiển thị bảng dữ liệu, biểu đồ nhiệt ma trận (heat map), biểu đồ phân tán (scatter plot) và biểu đồ thanh phân tầng.
- Yêu cầu phi chức năng: Tính xác thực dữ liệu, tính đáp ứng giao diện, khả năng cô lập lỗi, tính tái lập thực nghiệm 100%.
- Ngăn xếp công nghệ:
  - Back-end: PHP (Thuần/OOP, không framework nặng).
  - Cơ sở dữ liệu: MySQL (InnoDB, khóa ngoại, chỉ mục composite tối ưu).
  - Front-end: HTML5, CSS3, Bootstrap 5, JavaScript (ES6+), jQuery, AJAX.
  - Trực quan hóa chính của dashboard: Apache ECharts.
  - Môi trường đối chứng: D3.js, Chart.js, Apache ECharts.

## 6. Kiến Trúc và Hiện Thực Hệ Thống (System Architecture and Implementation)
- **6.1 Kiến trúc tổng thể:** Mô hình kiến trúc phân lớp hướng dịch vụ (Service-Layer Architecture) kết hợp giao tiếp REST/AJAX.
- **6.2 Tiếp nhận và tiền xử lý dữ liệu (Data Ingestion):** Cơ chế phân tích định dạng giỏ hàng (Basket CSV/TXT) và phân tích định dạng phân loại (Mushroom Parser).
- **6.3 Tầng lưu trữ bền vững (Persistence Layer):** Lược đồ cơ sở dữ liệu quan hệ (`datasets`, `dataset_transactions`, `transaction_items`, `mining_runs`, `frequent_itemsets`, `association_rules`, `itemset_level_metrics`).
- **6.4 Động cơ khai phá Apriori (Apriori Engine):** Thiết kế hướng đối tượng tách biệt các dịch vụ `CandidateJoiner`, `CandidatePruner`, `SupportCounter`, `FrequentFilter`.
- **6.5 Động cơ sinh luật kết hợp (Association Rule Generator):** Thuật toán duyệt sinh luật từ tập mục phổ biến và tính toán chỉ số support/confidence/lift.
- **6.6 Giao diện lập trình ứng dụng (HTTP API):** Thiết kế các endpoint `/api/datasets.php`, `/api/mining.php` với cấu trúc JSON chuẩn hóa, mã lỗi HTTP rõ ràng và cơ chế phân trang / giới hạn trả về.
- **6.7 Bảng điều khiển web tương tác (Interactive Dashboard):** Tổ chức giao diện Bootstrap đáp ứng, tích hợp điều khiển bất đồng bộ AJAX và biểu đồ trực quan hóa ECharts.

## 7. Phương Pháp Nghiên Cứu Thực Nghiệm (Research Methodology)
- **7.1 Tập dữ liệu thực nghiệm:** Bộ dữ liệu chuẩn UCI Mushroom (`agaricus-lepiota.data`, 8,124 giao dịch, 23 trường thuộc tính, 119 mục phân loại chuẩn hóa).
- **7.2 Lịch sử hiệu chỉnh ma trận ngưỡng hỗ trợ:** Minh bạch quá trình chuyển đổi từ ma trận sơ bộ không khả thi sang ma trận chính thức $[0.60, 0.50, 0.45, 0.40, 0.35]$.
- **7.3 Giao thức thực nghiệm khai phá (RQ1 / RQ2):** Chế độ chạy hình thức (10 lần lặp độc lập sau 2 lần chạy làm nóng), trộn ngẫu nhiên tất định (seed = 42), đo lường thời gian thực thi và số liệu cắt tỉa.
- **7.4 Giao thức đo kiểm trực quan hóa (RQ3):** Môi trường trình duyệt cô lập (Microsoft Edge 151, $1440 \times 900$, DPR 1.0), khối lượng dữ liệu điểm phân tán xác định ($N \in [100, 1000, 5000, 10000]$), giao thức chuẩn hóa `render-to-two-frame-observation latency`.
- **7.5 Thống kê phi tham số và tính tái lập:** Sử dụng Trung vị (Median) và Khoảng liên phân vị (IQR) theo quy chuẩn bản lề Tukey; đóng băng mã nguồn và dữ liệu bằng mã băm SHA-256.

## 8. Kết Quả Thực Nghiệm (Experimental Results)
- **8.1 Kết quả RQ1 (Ảnh hưởng của ngưỡng hỗ trợ):** Phân tích chi tiết qua Bảng T1, Hình F1 (thời gian thực thi), Hình F2 (không gian ứng viên) và Hình F3 (khối lượng mẫu trích xuất).
- **8.2 Kết quả RQ2 (Hiệu quả cắt tỉa Apriori):** Phân tích qua Bảng T2, T2b và Hình F4 (động thái cắt tỉa phân tầng cho toàn bộ 5 ngưỡng hỗ trợ).
- **8.3 Kết quả RQ3 (Hiệu năng trực quan hóa đối chứng):** Phân tích qua Bảng T3, Hình F5 (độ trễ khởi tạo) và Hình F6 (độ trễ cập nhật tại chỗ).

## 9. Thảo Luận (Discussion)
- Phân tích mối liên hệ giữa động thái cắt tỉa thuật toán và hành vi hệ thống web thực tế.
- Ý nghĩa của việc lựa chọn công nghệ trực quan hóa đối với trải nghiệm người dùng bảng điều khiển web.
- Đánh giá kiến trúc phân lớp PHP/MySQL trong bài toán khai phá dữ liệu tương tác.

## 10. Đe Dọa Giá Trị Thực Nghiệm và Hạn Chế (Threats to Validity & Limitations)
- Hạn chế của thực nghiệm khai phá: Đơn tập dữ liệu, ma trận hỗ trợ thu hẹp, thiếu đường cơ sở đo thời gian không cắt tỉa, cố định ngưỡng tin cậy.
- Hạn chế của thực nghiệm trực quan hóa: Ràng buộc kiến trúc Canvas/SVG, hiện tượng lượng tử hóa khung hình (frame-quantization), bố cục biên tự động của thư viện, dao động thu gom rác (GC) của trình duyệt.

## 11. Kết Luận (Conclusion)
- Tóm tắt các kết quả đạt được về mặt xây dựng phần mềm và đóng góp thực nghiệm.
- Khẳng định tính hoàn thiện và khả thi của hệ thống bảng điều khiển web tương tác.

## 12. Hướng Phát Triển Tương Lai (Future Work)
- Tích hợp động cơ FP-Growth song song để so sánh trực tiếp.
- Mở rộng hỗ trợ tập dữ liệu giao dịch bán lẻ thưa quy mô lớn.
- Bổ sung cơ chế bộ đệm phân tán (caching) và tối ưu hóa xử lý nền cho các tác vụ khai phá nặng.

## 13. Tài Liệu Tham Khảo (References)
- Danh mục tài liệu học thuật và tài liệu kỹ thuật chuẩn xác.

## Phụ Lục (Appendix)
- Bảng T2b: Chi tiết số liệu cắt tỉa phân tầng cho toàn bộ 31 mức $k$.
- Bảng tra cứu nguồn gốc bằng chứng (Evidence Manifest Checksums).
