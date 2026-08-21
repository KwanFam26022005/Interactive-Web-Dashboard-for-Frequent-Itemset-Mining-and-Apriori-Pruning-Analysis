# Danh Mục Tài Liệu Tham Khảo (Authoritative Bibliography)

Tài liệu này tổng hợp danh mục tài liệu tham khảo được sử dụng trong Báo cáo Giữa kỳ. Mỗi trường siêu dữ liệu trích dẫn dưới đây được giới hạn trong phạm vi thông tin đã được xác thực từ nguồn thẩm quyền chính thức tương ứng; các dữ liệu thực nghiệm và dẫn xuất nội bộ của dự án được theo dõi riêng biệt trong hồ sơ bằng chứng dự án.

---

## 1. Tài Liệu Học Thuật Khởi Nguồn & Nền Tảng (Academic Primary & Secondary Sources)

1. **[1] Agrawal, R., Imieliński, T., & Swami, A. (1993).**  
   *Mining Association Rules between Sets of Items in Large Databases.*  
   In *Proceedings of the 1993 ACM SIGMOD International Conference on Management of Data (SIGMOD '93)*, Washington, D.C., USA, May 26–28, 1993, pp. 207–216.  
   **DOI:** [10.1145/170035.170072](https://doi.org/10.1145/170035.170072)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Định nghĩa hình thức bài toán khai phá luật kết hợp, không gian mục, độ hỗ trợ (*support*) và độ tin cậy (*confidence*).

2. **[2] Agrawal, R., & Srikant, R. (1994).**  
   *Fast Algorithms for Mining Association Rules in Large Databases.*  
   In *Proceedings of the 20th International Conference on Very Large Data Bases (VLDB '94)*, Santiago, Chile, September 12–15, 1994, pp. 487–499.  
   **Authoritative URL:** [https://www.vldb.org/conf/1994/P487.PDF](https://www.vldb.org/conf/1994/P487.PDF)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Thuật toán Apriori, nguyên lý tính chất phản đơn điệu (*Apriori anti-monotonicity principle*), thủ tục sinh ứng viên $L_{k-1} \Join L_{k-1}$ và cơ chế cắt tỉa tập con không phổ biến.

3. **[3] Han, J., Pei, J., & Yin, Y. (2000).**  
   *Mining Frequent Patterns without Candidate Generation.*  
   In *Proceedings of the 2000 ACM SIGMOD International Conference on Management of Data (SIGMOD '00)*, Dallas, TX, USA, May 15–18, 2000, pp. 1–12.  
   **DOI:** [10.1145/342009.335372](https://doi.org/10.1145/342009.335372)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Nền tảng lý thuyết thuật toán FP-Growth, cấu trúc dữ liệu cây tiền tố liên kết bộ nhớ (*FP-tree*) và khai phá mẫu tăng trưởng không sinh ứng viên tường minh.

4. **[4] Tan, P.-N., Steinbach, M., Karpatne, A., & Kumar, V. (2018).**  
   *Introduction to Data Mining (2nd Edition).*  
   Pearson, Boston, MA, USA.  
   **ISBN-13:** 978-0133128901  
   **Phân loại:** `ACADEMIC_SECONDARY`  
   **Nội dung hỗ trợ:** Thước đo độ nâng (*lift*), phân tích tương quan phụ thuộc và cấu trúc không gian mạng lưới tập con (*itemset lattice*).

5. **[5] Bostock, M., Ogievetsky, V., & Heer, J. (2011).**  
   *D³: Data-Driven Documents.*  
   *IEEE Transactions on Visualization and Computer Graphics*, 17(12), pp. 2301–2309, Dec. 2011.  
   **DOI:** [10.1109/TVCG.2011.185](https://doi.org/10.1109/TVCG.2011.185)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Mô hình biến đổi tài liệu hướng dữ liệu (DOM/SVG) và kiến trúc trực quan hóa web dựa trên tiêu chuẩn web mở.

6. **[6] Li, D., Mei, H., Shen, Y., Su, S., Zhang, W., Wang, J., Zu, M., & Chen, W. (2018).**  
   *ECharts: A Declarative Framework for Rapid Construction of Web-based Visualization.*  
   *Visual Informatics*, 2(2), pp. 136–146, June 2018.  
   **DOI:** [10.1016/j.visinf.2018.04.011](https://doi.org/10.1016/j.visinf.2018.04.011)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Kiến trúc khai báo trực quan hóa dữ liệu web và nền tảng hiển thị của ECharts.

---

## 2. Bộ Dữ Liệu Chuẩn Thực Nghiệm (Benchmark Dataset)

7. **[7] UCI Machine Learning Repository: Mushroom Data Set.**  
   *Mushroom [Dataset].* (1981). UCI Machine Learning Repository.  
   **Nguồn gốc & Đóng góp:** Donated by Jeff Schlimmer (1987). Derived from *The Audubon Society Field Guide to North American Mushrooms* (1981).  
   **Dataset ID:** 73  
   **DOI:** [10.24432/C5959T](https://doi.org/10.24432/C5959T)  
   **License:** Creative Commons Attribution 4.0 International (CC BY 4.0)  
   **URL:** [https://archive.ics.uci.edu/dataset/73/mushroom](https://archive.ics.uci.edu/dataset/73/mushroom)  
   **Phân loại:** `DATASET`  
   **Phạm vi siêu dữ liệu xuất bản gốc được hỗ trợ:** 8,124 bản ghi, 22 thuộc tính mô tả phân loại + 1 thuộc tính phân lớp, xử lý giá trị khuyết thiếu. *(Ghi chú: Việc ánh xạ thành 23 trường vật lý $c_1..c_{23}$ và định lượng 119 mục phân biệt là dẫn xuất nội bộ từ manifest của dự án).*

---

## 3. Thư Viện Phần Mềm Trực Quan Hóa & Front-End (Software Packages)

8. **[8] Bostock, M., & D3 Contributors (2024).**  
   *D3.js: JavaScript library for visualizing data (Version 7.9.0).*  
   **License:** ISC License  
   **Authoritative Project Source:** [https://d3js.org/](https://d3js.org/) | GitHub: [https://github.com/d3/d3](https://github.com/d3/d3)  
   **Distribution Package:** `npm:d3@7.9.0`  
   **Phân loại:** `SOFTWARE`  
   **Nội dung hỗ trợ:** Thư viện đối chứng SVG trong thực nghiệm RQ3.

9. **[9] Chart.js Open Source Project (2025).**  
   *Chart.js: Simple yet flexible JavaScript charting for designers & developers (Version 4.4.8).*  
   **Release Date:** February 2025  
   **License:** MIT License  
   **Authoritative Project Source:** [https://www.chartjs.org/](https://www.chartjs.org/) | GitHub: [https://github.com/chartjs/Chart.js](https://github.com/chartjs/Chart.js)  
   **Distribution Package:** `npm:chart.js@4.4.8`  
   **Phân loại:** `SOFTWARE`  
   **Nội dung hỗ trợ:** Thư viện đối chứng Canvas trong thực nghiệm RQ3.

10. **[10] The Apache Software Foundation (2025).**  
    *Apache ECharts: An Open Source JavaScript Visualization Library (Version 5.6.0).*  
    **Release Date:** January 2025  
    **License:** Apache License 2.0  
    **Authoritative Project Source:** [https://echarts.apache.org/](https://echarts.apache.org/) | GitHub: [https://github.com/apache/echarts](https://github.com/apache/echarts)  
    **Distribution Package:** `npm:echarts@5.6.0`  
    **Phân loại:** `SOFTWARE`  
    **Nội dung hỗ trợ:** Thư viện trực quan hóa chính của bảng điều khiển web và đối chứng Canvas trong RQ3.

11. **[11] Bootstrap Authors (2025).**  
    *Bootstrap: Powerful, extensible, and feature-packed frontend toolkit (Version 5.3.8).*  
    **Release Date:** August 25, 2025  
    **License:** MIT License  
    **Authoritative Project Source:** [https://getbootstrap.com/](https://getbootstrap.com/) | GitHub: [https://github.com/twbs/bootstrap](https://github.com/twbs/bootstrap)  
    **Distribution Channel:** `npm:bootstrap@5.3.8` (vendored distribution in `public/assets/vendor/bootstrap/`)  
    **Phân loại:** `SOFTWARE`  
    **Nội dung hỗ trợ:** Khung giao diện web đáp ứng của bảng điều khiển.

12. **[12] OpenJS Foundation (2023).**  
    *jQuery: Fast, small, and feature-rich JavaScript library (Version 3.7.1).*  
    **Release Date:** August 28, 2023  
    **License:** MIT License  
    **Authoritative Project Source:** [https://jquery.com/](https://jquery.com/) | GitHub: [https://github.com/jquery/jquery](https://github.com/jquery/jquery)  
    **Distribution Channel:** jQuery Official CDN build (vendored distribution in `public/assets/vendor/jquery/`)  
    **Phân loại:** `SOFTWARE`  
    **Nội dung hỗ trợ:** Xử lý sự kiện DOM và truyền thông AJAX bất đồng bộ phía client.

---

## 4. Ngôn Ngữ, Cơ Sở Dữ Liệu & Tiêu Chuẩn Công Nghệ (Standards & Documentation)

13. **[13] The PHP Group.**  
    *PHP Manual: Language Reference & Runtime Architecture.* n.d.  
    **URL:** [https://www.php.net/docs.php](https://www.php.net/docs.php) (Accessed: 2026-08-21)  
    **Phân loại:** `DOCUMENTATION`  
    **Môi trường thực thi đo lường:** PHP 8.3.30 (CLI) (Ghi nhận từ `environment_manifest.json`) / Khả năng tương thích mục tiêu: PHP 8.2+  
    **Nội dung hỗ trợ:** Ngôn ngữ lập trình phụ trợ, cú pháp đối tượng và môi trường thực thi tính toán.

14. **[14] Oracle Corporation.**  
    *MySQL 8.4 Reference Manual: InnoDB Storage Engine & Performance.* n.d.  
    **URL:** [https://dev.mysql.com/doc/refman/8.4/en/](https://dev.mysql.com/doc/refman/8.4/en/) (Accessed: 2026-08-21)  
    **Phân loại:** `DOCUMENTATION`  
    **Môi trường cơ sở dữ liệu đo lường:** MySQL 8.4.3 (Ghi nhận từ `environment_manifest.json`)  
    **Nội dung hỗ trợ:** Hệ quản trị cơ sở dữ liệu quan hệ, cấu trúc bảng InnoDB và chỉ mục khóa ngoại.

15. **[15] Bray, T. (Ed.) (2017).**  
    *RFC 8259: The JavaScript Object Notation (JSON) Data Interchange Format.*  
    **Standards Track:** Internet Engineering Task Force (IETF), RFC 8259, STD 90, December 2017.  
    **DOI:** [10.17487/RFC8259](https://doi.org/10.17487/RFC8259)  
    **URL:** [https://www.rfc-editor.org/info/rfc8259](https://www.rfc-editor.org/info/rfc8259)  
    **Phân loại:** `STANDARD`  
    **Nội dung hỗ trợ:** Chuẩn định dạng trao đổi dữ liệu JSON giữa máy chủ PHP và máy khách AJAX.
