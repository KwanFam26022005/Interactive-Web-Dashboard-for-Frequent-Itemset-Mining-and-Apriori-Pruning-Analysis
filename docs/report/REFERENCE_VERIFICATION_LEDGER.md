# Sổ Cái Thẩm Định Nguồn Tham Khảo (Reference Verification Ledger)

**Dự án:** Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis  
**Mã nguồn & Dữ liệu:** `D:\Projects\fim-dashboard`  
**Giai đoạn:** Phase 5B — Authoritative Literature, Citation, and Bibliographic Verification  
**Thời gian thẩm định:** 2026-08-21  

Tài liệu này là hồ sơ kiểm toán (*audit ledger*) ghi nhận chi tiết quá trình đối chiếu, nguồn gốc thẩm định, định danh bền vững và phạm vi tuyên bố học thuật của từng mục tài liệu tham khảo được sử dụng trong Báo cáo Giữa kỳ.

---

| ID | Trích Dẫn Rút Gọn (Reference Key) | Phân Loại (Type) | Nguồn Thẩm Định Gốc (Authoritative Source) | Định Danh Bền Vững (DOI / URL / Package) | Dữ Liệu Siêu Thông Tin Đã Xác Thực (Verified Metadata) | Các Tuyên Bố Học Thuật Được Hỗ Trợ (Claims Supported) | Các Mục Sử Dụng Trong Báo Cáo (Draft Sections) | Trạng Thái Thẩm Định (Verification Status) |
| :---: | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :---: |
| **[1]** | Agrawal, Imieliński, & Swami (1993) | `ACADEMIC_PRIMARY` | ACM Digital Library / SIGMOD '93 Proceedings | DOI: `10.1145/170035.170072` | ACM SIGMOD 1993, Washington D.C., pp. 207–216. | Khởi nguồn bài toán khai phá luật kết hợp, định nghĩa tập mục, giao dịch, độ hỗ trợ (*support*), độ tin cậy (*confidence*). | Mục 1, Mục 4.1, Mục 4.2, Mục 4.3 | **VERIFIED_AUTHORITATIVE** |
| **[2]** | Agrawal & Srikant (1994) | `ACADEMIC_PRIMARY` | VLDB Endowment / VLDB '94 Proceedings | URL: `https://www.vldb.org/conf/1994/P487.PDF` | VLDB 1994, Santiago, Chile, pp. 487–499. | Thuật toán Apriori, nguyên lý phản đơn điệu (*anti-monotonicity*), bước kết nối $L_{k-1} \Join L_{k-1}$, bước cắt tỉa tập con không phổ biến. | Mục 1, Mục 4.5, Mục 4.6, Mục 6.4 | **VERIFIED_AUTHORITATIVE** |
| **[3]** | Han, Pei, & Yin (2000) | `ACADEMIC_PRIMARY` | ACM Digital Library / SIGMOD 2000 Proceedings | DOI: `10.1145/342009.335372` | ACM SIGMOD 2000, Dallas, TX, pp. 1–12. | Thuật toán FP-Growth, cấu trúc dữ liệu cây tiền tố nén (*FP-tree*), khai phá mẫu tăng trưởng chia để trị không sinh ứng viên tường minh. | Mục 4.7, Mục 12 | **VERIFIED_AUTHORITATIVE** |
| **[4]** | Tan, Steinbach, Karpatne, & Kumar (2018) | `ACADEMIC_SECONDARY` | Pearson Higher Education Official Catalog | ISBN-13: `978-0133128901` | Pearson, 2nd Edition, 2018, Boston, MA. | Định nghĩa toán học và ý nghĩa thống kê của độ nâng (*lift*), tương quan độc lập, không gian mạng lưới tập con (*itemset lattice*). | Mục 4.4, Mục 4.5 | **VERIFIED_AUTHORITATIVE** |
| **[5]** | Bostock, Ogievetsky, & Heer (2011) | `ACADEMIC_PRIMARY` | IEEE Xplore / IEEE TVCG | DOI: `10.1109/TVCG.2011.185` | IEEE TVCG, Vol. 17, No. 12, pp. 2301–2309, Dec. 2011. | Mô hình biến đổi tài liệu hướng dữ liệu (DOM/SVG) và kiến trúc trực quan hóa dựa trên phần tử đồ họa vector. | Mục 4.8, Mục 7.4, Mục 8.3 | **VERIFIED_AUTHORITATIVE** |
| **[6]** | Li et al. (2018) | `ACADEMIC_PRIMARY` | Elsevier ScienceDirect / Visual Informatics | DOI: `10.1016/j.visinf.2018.04.011` | Visual Informatics, Vol. 2, No. 2, pp. 136–146, June 2018. | Kiến trúc khai báo trực quan hóa dữ liệu web và nền tảng hiển thị của ECharts. | Mục 4.8, Mục 5.2 | **VERIFIED_AUTHORITATIVE** |
| **[7]** | UCI Machine Learning Repository (1987) | `DATASET` | UCI ML Repository Official Landing Page | DOI: `10.24432/C59591` | Donor: Jeff Schlimmer, 1987. $N = 8,124$ giao dịch, 23 trường phân loại vật lý (gồm lớp $c_1$ và 22 thuộc tính $c_2..c_{23}$), 119 mục phân loại chuẩn. License: CC BY 4.0. | Thuộc tính, nguồn gốc, kích thước và cấu trúc dữ liệu của tập thực nghiệm chuẩn UCI Mushroom (`agaricus-lepiota.data`). | Mục 7.1, Mục 10.1 | **VERIFIED_AUTHORITATIVE** |
| **[8]** | D3.js v7.9.0 Software Package | `SOFTWARE` | npm Registry / GitHub Release d3/d3 | `npm:d3@7.9.0` (ISC License) | Version 7.9.0, SHA-256 đối chiếu khớp `f2094bbf...` | Phiên bản phần mềm D3.js sử dụng trong thực nghiệm đối chứng SVG của RQ3. | Mục 3.2, Mục 7.4, Mục 8.3 | **VERIFIED_AUTHORITATIVE** |
| **[9]** | Chart.js v4.4.8 Software Package | `SOFTWARE` | npm Registry / GitHub Release chartjs/Chart.js | `npm:chart.js@4.4.8` (MIT License) | Version 4.4.8, SHA-256 đối chiếu khớp `c40877e8...` | Phiên bản phần mềm Chart.js sử dụng trong thực nghiệm đối chứng Canvas của RQ3. | Mục 3.2, Mục 7.4, Mục 8.3 | **VERIFIED_AUTHORITATIVE** |
| **[10]** | Apache ECharts v5.6.0 Software Package | `SOFTWARE` | Apache Software Foundation / npm Registry | `npm:echarts@5.6.0` (Apache-2.0 License) | Version 5.6.0, SHA-256 đối chiếu khớp `bf4a2235...` | Phiên bản thư viện trực quan hóa chính của bảng điều khiển web và đối chứng Canvas trong RQ3. | Mục 1, Mục 5.2, Mục 6.7, Mục 8.3 | **VERIFIED_AUTHORITATIVE** |
| **[11]** | Bootstrap v5.3.8 Software Package | `SOFTWARE` | npm Registry / jsDelivr / Vendor Manifest | `npm:bootstrap@5.3.8` (MIT License) | Version 5.3.8 pinned in `public/assets/vendor/bootstrap/` | Khung giao diện web đáp ứng của bảng điều khiển. | Mục 1, Mục 5.2, Mục 6.7 | **VERIFIED_AUTHORITATIVE** |
| **[12]** | jQuery v3.7.1 Software Package | `SOFTWARE` | OpenJS Foundation / jQuery CDN / Vendor Manifest | Version 3.7.1 (MIT License) | SHA-256 đối chiếu khớp `fc9a93dd...` | Xử lý sự kiện DOM và truyền thông AJAX bất đồng bộ phía client. | Mục 1, Mục 5.2, Mục 6.7 | **VERIFIED_AUTHORITATIVE** |
| **[13]** | PHP Manual & Runtime | `DOCUMENTATION` | The PHP Group Official Documentation | URL: `https://www.php.net/` | Formal runtime: PHP 8.3.30 (CLI) / Target compatibility: PHP 8.2+ | Môi trường lập trình phụ trợ, cú pháp hướng đối tượng và cơ chế thực thi tính toán. | Mục 1, Mục 5.2, Mục 6.1 | **VERIFIED_AUTHORITATIVE** |
| **[14]** | MySQL 8.4 Reference Manual | `DOCUMENTATION` | Oracle Corporation Documentation | URL: `https://dev.mysql.com/doc/` | Formal runtime: MySQL 8.4.3 (InnoDB Engine) | Hệ quản trị cơ sở dữ liệu quan hệ, lược đồ bảng InnoDB và chỉ mục composite. | Mục 1, Mục 5.2, Mục 6.3 | **VERIFIED_AUTHORITATIVE** |
| **[15]** | IETF RFC 8259 Standard | `STANDARD` | IETF RFC Editor | DOI: `10.17487/RFC8259` | STD 90 / RFC 8259, Tim Bray (Ed.), Dec 2017. | Tiêu chuẩn định dạng dữ liệu JSON trao đổi giữa máy chủ PHP và máy khách AJAX. | Mục 6.2, Mục 6.6 | **VERIFIED_AUTHORITATIVE** |

---

## Tổng Kết Kiểm Toán

- **Tổng số mục tài liệu tham khảo:** 15 mục.
- **Số mục đã xác thực nguồn gốc chính thức (VERIFIED_AUTHORITATIVE):** 15 / 15 (100%).
- **Số mục chưa giải quyết:** 0.
- **Độ tin cậy trích dẫn:** Đạt chuẩn thẩm định học thuật chính thức cho Báo cáo Giữa kỳ.
