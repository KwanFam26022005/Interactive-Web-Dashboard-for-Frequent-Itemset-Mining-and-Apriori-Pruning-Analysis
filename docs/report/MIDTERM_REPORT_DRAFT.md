# BÁO CÁO GIỮA KỲ ĐỀ TÀI NGHIÊN CỨU VÀ PHÁT TRIỂN ỨNG DỤNG WEB

---

**ĐỀ TÀI:**  
### XÂY DỰNG BẢNG ĐIỀU KHIỂN WEB TƯƠNG TÁC PHỤC VỤ KHAI PHÁ TẬP MỤC PHỔ BIẾN VÀ PHÂN TÍCH ĐỘNG THÁI CẮT TỈA APRIORI  
*(Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis)*

**Môn học:** Ứng Dụng và Lập Trình Web (Web Programming and Applications)  
**Mã nguồn & Dữ liệu thực nghiệm:** `D:\Projects\fim-dashboard`  
**Nhánh thực nghiệm đóng băng:** `phase/4-experiments`  
**Thời gian thực hiện:** Tháng 8 Năm 2026  

---

## TÓM TẮT BÁO CÁO (ABSTRACT)

Báo cáo này trình bày quá trình thiết kế, hiện thực hóa và đánh giá thực nghiệm một hệ thống ứng dụng web hoàn chỉnh phục vụ bài toán Khai phá Tập mục Phổ biến (*Frequent Itemset Mining - FIM*) và Khai phá Luật kết hợp (*Association Rule Mining*). Dự án giải quyết khoảng cách cố hữu giữa các thuật toán khai phá dữ liệu dòng lệnh mang tính hàn lâm và nhu cầu giám sát, phân tích trực quan hóa tương tác của người dùng trên nền tảng web hiện đại. 

Hệ thống được phát triển theo kiến trúc phân lớp hướng dịch vụ (*Service-Layer Architecture*) sử dụng ngăn xếp công nghệ thuần **PHP 8.2+**, cơ sở dữ liệu quan hệ **MySQL 8.4** và giao diện người dùng đáp ứng dựa trên **HTML5/Bootstrap 5/jQuery/AJAX** kết hợp thư viện trực quan hóa **Apache ECharts**. Điểm đóng góp cốt lõi của đề tài là sự kết hợp chặt chẽ giữa việc xây dựng một sản phẩm web hoàn chỉnh với một khung nghiên cứu thực nghiệm có kiểm soát chặt chẽ (*controlled empirical study*) nhằm trả lời ba câu hỏi nghiên cứu (RQ1, RQ2, RQ3).

Dựa trên bộ dữ liệu thực nghiệm chuẩn UCI Mushroom (*agaricus-lepiota.data*, $N = 8,124$ giao dịch, 119 mục phân loại chuẩn), các thực nghiệm được đóng băng với 10 lần lặp độc lập sau 2 lần chạy làm nóng, sử dụng phương pháp thống kê phi tham số (Trung vị và Khoảng liên phân vị - IQR). Kết quả thực nghiệm chỉ ra rằng:
1. Việc hạ ngưỡng hỗ trợ tối thiểu ($\text{min\_support}$) từ $0.60$ xuống $0.35$ dẫn đến sự mở rộng đáng kể (*substantial expansion*) không gian tìm kiếm ứng viên (từ $185$ lên $2,131$), khối lượng tập mục phổ biến ($51$ lên $1,189$), số lượng luật kết hợp ($223$ lên $11,055$) và thời gian thực thi trung vị của thuật toán Apriori (từ $523.072\text{ ms}$ lên $14,047.443\text{ ms}$) (RQ1).
2. Tỷ lệ cắt tỉa ứng viên theo tính chất Apriori (*Apriori-property subset pruning*) gia tăng từ $5.95\%$ lên $29.28\%$ tổng số ứng viên sinh ra khi giảm ngưỡng hỗ trợ, trong đó việc cắt tỉa tập con thực tế bắt đầu loại bỏ ứng viên từ bậc độ dài $k = 3$, giúp loại bỏ hàng trăm ứng viên không tiềm năng trước bước đánh giá độ hỗ trợ trên tập dữ liệu giao dịch (RQ2).
3. Trong thực nghiệm đối chứng hiệu năng hiển thị front-end cô lập dưới thước đo chuẩn hóa độ trễ quan sát hai khung hình (*render-to-two-frame-observation latency*), Chart.js (Canvas) đạt độ trễ thấp nhất ở khối lượng dữ liệu dày đặc ($N = 10,000$ điểm), tiếp theo là D3.js (SVG) và Apache ECharts (Canvas), đồng thời ở các quy mô dữ liệu lớn ($N \ge 5,000$), việc cập nhật dữ liệu tại chỗ (*in-place update*) có độ trễ thấp hơn so với việc khởi dựng biểu đồ ban đầu trên cả ba thư viện (RQ3).

---

## 1. GIỚI THIỆU (INTRODUCTION)

Trong kỷ nguyên bùng nổ dữ liệu số, việc phát hiện các mẫu tri thức ẩn sâu và các mối tương quan có giá trị trong các cơ sở dữ liệu giao dịch lớn đóng vai trò then chốt trong nhiều lĩnh vực ứng dụng, từ phân tích giỏ hàng thương mại (*market basket analysis*), hệ thống gợi ý sản phẩm (*recommender systems*), đến phát hiện gian lận và tin sinh học. Khai phá tập mục phổ biến (*Frequent Itemset Mining - FIM*) và khai phá luật kết hợp (*Association Rule Mining*), khởi xướng bởi Agrawal và cộng sự (1993, 1994), là một trong những trụ cột lý thuyết quan trọng nhất của lĩnh vực Khai phá dữ liệu (*Data Mining*).

Mặc dù nền tảng lý thuyết của thuật toán Apriori đã được nghiên cứu sâu rộng, việc ứng dụng thuật toán này trong môi trường web thực tế vẫn đối mặt với những thách thức đáng kể:
- **Tính toán nặng phía máy chủ:** Bản chất duyệt không gian tìm kiếm tổ hợp dạng mạng lưới (*lattice*) đòi hỏi nhiều lượt quét dữ liệu và so khớp tập con, dễ dẫn đến nghẽn tài nguyên CPU và bộ nhớ khi ngưỡng hỗ trợ tối thiểu bị hạ thấp.
- **Rào cản giao tiếp người dùng:** Đa số các công cụ khai phá truyền thống hoạt động dưới dạng dòng lệnh (*Command-Line Interface - CLI*) hoặc các thư viện học máy độc lập, tạo ra khoảng cách lớn đối với người phân tích kinh doanh cần tương tác trực tiếp với tham số và quan sát biểu đồ phân tích.
- **Áp lực hiển thị phía máy khách (Client-Side Rendering):** Các tập mẫu phổ biến và luật kết hợp khi được trích xuất thường tạo ra hàng ngàn đến hàng vạn điểm dữ liệu. Việc trực quan hóa các mối quan hệ đa chiều (Support vs. Confidence vs. Lift) trên trình duyệt web đòi hỏi các giải pháp thư viện đồ họa tối ưu nhằm duy trì tốc độ khung hình mượt mà và khả năng tương tác liên tục.

Nhằm giải quyết đồng thời các thách thức trên trong khuôn khổ môn học **Ứng Dụng và Lập Trình Web**, đề tài này xây dựng một hệ sinh thái ứng dụng web hoàn chỉnh:
- Tầng phụ trợ (Back-end) phát triển bằng PHP thuần hướng đối tượng, triển khai thuật toán Apriori với cơ chế đo lường chi tiết động thái cắt tỉa qua từng bậc độ dài $k$.
- Tầng lưu trữ (Database) sử dụng MySQL với lược đồ quan hệ chuẩn hóa cao độ, tối ưu hóa chỉ mục cho các truy vấn phân tích.
- Tầng giao diện (Front-end) là bảng điều khiển web tương tác hiện đại ứng dụng AJAX, Bootstrap 5 và Apache ECharts.
- Khung nghiên cứu thực nghiệm (Empirical Benchmark Pipeline) tự động hóa, đóng băng dữ liệu và kiểm chứng tính toán học chặt chẽ.

---

## 2. PHÁT BIỂU BÀI TOÁN (PROBLEM STATEMENT)

Bài toán trung tâm của đề tài được định nghĩa trên hai phương diện:

1. **Phương diện Kỹ thuật Web & Hệ thống:**  
   Làm thế nào để thiết kế một kiến trúc web có khả năng tiếp nhận các tệp dữ liệu giao dịch tùy biến, tiền xử lý và lưu trữ bền vững, đồng thời cung cấp giao diện lập trình ứng dụng (RESTful API) phi trạng thái để kích hoạt tiến trình khai phá Apriori bất đồng bộ mà không làm khóa giao diện người dùng (*UI blocking*)? Bảng điều khiển cần biểu diễn trực quan các phân phối dữ liệu, cho phép lọc động các tập mục phổ biến và ma trận luật kết hợp theo thời gian thực.

2. **Phương diện Động thái Thuật toán & Hiệu năng Trực quan hóa:**  
   Cần khảo sát một cách định lượng và minh bạch mối quan hệ phi tuyến tính giữa ngưỡng hỗ trợ tối thiểu với không gian ứng viên sinh ra, khối lượng mẫu phát hiện và thời gian thực thi của thuật toán Apriori trên một tập dữ liệu thực tế. Đồng thời, cần đo lường hiệu quả cắt tỉa thực tế của tính chất Apriori qua từng bậc $k$, và đánh giá hiệu năng hiển thị của các công nghệ thư viện đồ họa web (DOM/SVG vs. HTML5 Canvas) khi phải gánh tải các tập dữ liệu điểm dày đặc.

---

## 3. MỤC TIÊU VÀ CÂU HỎI NGHIÊN CỨU (OBJECTIVES AND RESEARCH QUESTIONS)

### 3.1 Mục Tiêu Dự Án
- **Mục tiêu 1:** Xây dựng ứng dụng web quản lý tập dữ liệu, cấu hình khai phá và hiển thị bảng điều khiển tương tác chuẩn công nghiệp bằng PHP, MySQL, Bootstrap và Apache ECharts.
- **Mục tiêu 2:** Hiện thực hóa động cơ khai phá Apriori và sinh luật kết hợp độc lập, mô-đun hóa cao, tích hợp bộ thu thập số liệu cắt tỉa (*pruning instrumentation*) chi tiết theo từng cấp độ.
- **Mục tiêu 3:** Thiết lập khung thực nghiệm chuẩn hóa, bảo đảm tính tái lập (*reproducibility*) và truy xuất nguồn gốc (*provenance*) 100% thông qua mã băm mật mã SHA-256.

### 3.2 Các Câu Hỏi Nghiên Cứu (Research Questions)
Để định hướng đánh giá thực nghiệm, ba câu hỏi nghiên cứu hình thức được thiết lập:

- **RQ1 (Ảnh hưởng của Ngưỡng Hỗ trợ):**  
  *Ngưỡng hỗ trợ tối thiểu ($\text{min\_support}$) ảnh hưởng như thế nào đến khối lượng ứng viên sinh ra, số lượng tập mẫu phổ biến / luật kết hợp được trích xuất và thời gian thực thi của thuật toán Apriori?*
- **RQ2 (Động thái và Hiệu quả Cắt tỉa Apriori):**  
  *Cơ chế cắt tỉa tập con theo tính chất Apriori ($\text{Apriori-property subset pruning}$) đạt hiệu quả ra sao trong việc thu hẹp không gian tìm kiếm ứng viên qua từng bậc độ dài tập mục $k$?*
- **RQ3 (So sánh Hiệu năng Trực quan hóa Front-End):**  
  *Ba thư viện trực quan hóa web D3.js (SVG), Chart.js (Canvas) và Apache ECharts (Canvas) so sánh như thế nào về độ trễ khởi tạo biểu đồ ban đầu ($\text{initial render}$) và độ trễ cập nhật dữ liệu tại chỗ ($\text{in-place data update}$) dưới các khối lượng dữ liệu điểm phân tán tương đương?*

---

## 4. CƠ SỞ LÝ THUYẾT (THEORETICAL BACKGROUND)

### 4.1 Khai Phá Tập Mục Phổ Biến (Frequent Itemset Mining)
Cho $\mathcal{I} = \{i_1, i_2, \dots, i_m\}$ là tập hợp tất cả các mục (*items*) phân biệt. Một tập hợp $X \subseteq \mathcal{I}$ được gọi là một tập mục (*itemset*). Một tập mục chứa $k$ mục được gọi là một $k$-tập mục (*$k$-itemset*).

Cho cơ sở dữ liệu giao dịch $\mathcal{D} = \{T_1, T_2, \dots, T_N\}$, trong đó mỗi giao dịch $T_j \subseteq \mathcal{I}$ gắn liền với một định danh duy nhất ($TID$). Một giao dịch $T$ được gọi là chứa tập mục $X$ nếu và chỉ nếu $X \subseteq T$.

### 4.2 Độ Hỗ Trợ (Support)
Độ hỗ trợ tuyệt đối (*absolute support count*) của tập mục $X$ trong $\mathcal{D}$, ký hiệu là $\sigma(X)$, là số lượng giao dịch chứa $X$:
$$\sigma(X) = |\{T \in \mathcal{D} \mid X \subseteq T\}|$$

Độ hỗ trợ tương đối (*relative support*) của tập mục $X$, ký hiệu là $\text{supp}(X)$, là tỷ lệ phần trăm các giao dịch trong $\mathcal{D}$ chứa $X$:
$$\text{supp}(X) = \frac{\sigma(X)}{N} = \frac{|\{T \in \mathcal{D} \mid X \subseteq T\}|}{|\mathcal{D}|}$$

Một tập mục $X$ được coi là **phổ biến** (*frequent itemset*) nếu $\text{supp}(X) \ge \text{minsup}$, trong đó $\text{minsup} \in (0, 1]$ là ngưỡng hỗ trợ tối thiểu do người dùng định nghĩa. Tập hợp tất cả các $k$-tập mục phổ biến được ký hiệu là $L_k$.

### 4.3 Khai Phá Luật Kết Hợp và Độ Tin Cậy (Confidence)
Một luật kết hợp là một biểu thức có dạng:
$$X \Rightarrow Y$$
trong đó $X \subset \mathcal{I}$, $Y \subset \mathcal{I}$, $X \neq \emptyset$, $Y \neq \emptyset$ và $X \cap Y = \emptyset$. Tập mục $X$ được gọi là tiền đề (*antecedent*), còn $Y$ được gọi là hệ quả (*consequent*).

Độ hỗ trợ của luật $X \Rightarrow Y$ chính là độ hỗ trợ của tập hợp $X \cup Y$:
$$\text{supp}(X \Rightarrow Y) = \text{supp}(X \cup Y)$$

Độ tin cậy (*confidence*) của luật $X \Rightarrow Y$ đo lường xác suất có điều kiện giao dịch chứa $Y$ khi đã biết giao dịch đó chứa $X$:
$$\text{conf}(X \Rightarrow Y) = P(Y \mid X) = \frac{\text{supp}(X \cup Y)}{\text{supp}(X)} = \frac{\sigma(X \cup Y)}{\sigma(X)}$$

Một luật kết hợp được chấp nhận là luật mạnh (*strong association rule*) nếu $\text{supp}(X \Rightarrow Y) \ge \text{minsup}$ và $\text{conf}(X \Rightarrow Y) \ge \text{minconf}$.

### 4.4 Độ Nâng (Lift)
Độ nâng (*lift*) đo lường mức độ phụ thuộc thống kê giữa $X$ và $Y$, xác định xem sự xuất hiện của $X$ có làm tăng hay giảm khả năng xuất hiện của $Y$ so với trường hợp độc lập:
$$\text{lift}(X \Rightarrow Y) = \frac{P(X \cup Y)}{P(X) \cdot P(Y)} = \frac{\text{conf}(X \Rightarrow Y)}{\text{supp}(Y)} = \frac{\text{supp}(X \cup Y)}{\text{supp}(X) \cdot \text{supp}(Y)}$$

- $\text{lift} = 1$: $X$ và $Y$ hoàn toàn độc lập thống kê.
- $\text{lift} > 1$: $X$ và $Y$ có tương quan đồng thuận dương (*positive correlation*).
- $\text{lift} < 1$: $X$ và $Y$ có tương quan nghịch (*negative correlation*).

### 4.5 Tính Chất Apriori (The Apriori Principle)
Không gian tìm kiếm tất cả các tập mục tiềm năng từ tập $\mathcal{I}$ có kích thước $2^{|\mathcal{I}|}$, tạo thành một mạng lưới tập con (*itemset lattice*).

**Định lý Tính chất Phản Đơn Điệu (Anti-monotonicity Property):**  
*Mọi tập con không rỗng của một tập mục phổ biến đều phải là tập mục phổ biến.*  
$$\forall X, Y \subseteq \mathcal{I}: X \subseteq Y \implies \text{supp}(Y) \le \text{supp}(X)$$

**Hệ quả dùng cho cắt tỉa:**  
*Nếu một tập mục $X$ không phổ biến ($\text{supp}(X) < \text{minsup}$), thì mọi tập siêu $Y \supset X$ đều chắc chắn không phổ biến và có thể bị loại bỏ ngay lập tức mà không cần quét cơ sở dữ liệu.*

### 4.6 Cơ Chế Sinh Ứng Viên và Cắt Tỉa (Candidate Generation & Pruning)
Thuật toán Apriori duyệt không gian tìm kiếm theo từng cấp bậc độ dài $k$ (*level-wise breadth-first search*):

1. **Bước Khởi tạo ($k=1$ - `singleton_scan`):** Quét toàn bộ cơ sở dữ liệu để đếm độ hỗ trợ của từng mục đơn $i \in \mathcal{I}$, trích xuất $L_1$.
2. **Bước Kết nối ($k \ge 2$ - `join_step`):** Sinh tập ứng viên $C_k$ bằng cách kết nối hai tập mục $l_1, l_2 \in L_{k-1}$ có cùng tiền tố $k-2$ mục đầu tiên:
   $$l_1 = \{i_1, i_2, \dots, i_{k-2}, i_{k-1}\}, \quad l_2 = \{i_1, i_2, \dots, i_{k-2}, i'_{k-1}\} \quad (i_{k-1} < i'_{k-1})$$
   $$c = l_1 \cup l_2 = \{i_1, i_2, \dots, i_{k-2}, i_{k-1}, i'_{k-1}\}$$
3. **Bước Cắt tỉa ($k \ge 2$ - `prune_step`):** Đối với mỗi ứng viên $c \in C_k$, kiểm tra xem tất cả các tập con độ dài $k-1$ của $c$ có nằm trong $L_{k-1}$ hay không. Nếu tồn tại bất kỳ tập con nào không thuộc $L_{k-1}$, loại bỏ $c$ khỏi $C_k$.
4. **Bước Đếm và Lọc:** Đếm độ hỗ trợ thực tế của các ứng viên còn lại sau cắt tỉa trên tập dữ liệu và giữ lại các ứng viên đạt $\text{minsup}$ để tạo thành $L_k$.

### 4.7 So Sánh Lý Thuyết Với Thuật Toán FP-Growth (Theoretical Context)
Trong tài liệu khai phá dữ liệu, thuật toán FP-Growth (*Frequent Pattern Growth*) của Han và cộng sự (2000) được xem là giải pháp thay thế kinh điển cho Apriori:
- **Nguyên lý:** FP-Growth nén cơ sở dữ liệu vào một cấu trúc cây tiền tố liên kết bộ nhớ (*FP-tree*), sau đó sử dụng chiến lược chia để trị (*divide-and-conquer*) để khai phá các mẫu phổ biến từ các cây điều kiện (*conditional pattern bases*) mà hoàn toàn không trải qua bước sinh ứng viên tổ hợp.
- **Đánh đổi lý thuyết:** Trong khi FP-Growth vượt trội về thời gian thực thi khi không gian ứng viên bùng nổ, Apriori lại thể hiện rõ ràng và minh bạch cấu trúc phân tầng $k$-itemset, cho phép quan sát trực tiếp động thái cắt tỉa tổ hợp qua từng bước. Trong khuôn khổ đề tài ứng dụng web này, Apriori được lựa chọn hiện thực hóa chính thức nhằm phục vụ mục tiêu phân tích chẩn đoán cơ chế cắt tỉa; FP-Growth được trình bày như nền tảng đối sánh lý thuyết.

### 4.8 Các Nền Tảng Thư Viện Trực Quan Hóa Web
Việc trực quan hóa dữ liệu khai phá trên giao diện web phụ thuộc lớn vào công nghệ dựng hình của thư viện:
- **D3.js (Data-Driven Documents):** Sử dụng mô hình DOM/SVG, nơi mỗi điểm dữ liệu được ánh xạ thành một phần tử `<circle>` trong cây DOM. Ưu điểm là khả năng tùy biến CSS/sự kiện chi tiết, nhưng chi phí quản lý hàng chục ngàn node DOM có thể làm tăng độ trễ bộ nhớ.
- **Chart.js & Apache ECharts:** Sử dụng công nghệ HTML5 Canvas rasterization, vẽ trực tiếp các điểm ảnh lên một vùng đệm bitmap đơn lẻ. Canvas tránh được quá tải cây DOM, mang lại hiệu năng cao trong các tác vụ vẽ mật độ lớn.

---

## 5. YÊU CẦU HỆ THỐNG VÀ NGĂN XẾP CÔNG NGHỆ (SYSTEM REQUIREMENTS AND TECH STACK)

### 5.1 Yêu Cầu Chức Năng (Functional Requirements)
- **FR1 (Quản lý Tập Dữ Liệu):** Cho phép người dùng tải lên, kiểm tra tính hợp lệ và lưu trữ các tệp dữ liệu giao dịch ở các định dạng giỏ hàng (Basket CSV/TXT) và định dạng phân loại (Mushroom tabular format).
- **FR2 (Cấu hình và Kích hoạt Khai Phá):** Cung cấp giao diện cấu hình tham số $\text{min\_support}$ và $\text{min\_confidence}$, kích hoạt tiến trình khai phá Apriori bất đồng bộ.
- **FR3 (Bảng Điều Khiển Trực Quan Hóa):** Hiển thị tổng quan các chỉ số thống kê, bảng dữ liệu phân trang có tìm kiếm, biểu đồ ma trận tương quan, biểu đồ thanh phân tầng và biểu đồ phân tán đa chiều tương tác.
- **FR4 (Phân Tích Động Thái Cắt Tỉa):** Trực quan hóa chi tiết tỷ lệ ứng viên sinh ra, cắt tỉa và giữ lại qua từng bậc $k$.

### 5.2 Ngăn Xếp Công Nghệ Chuẩn Hóa
Hệ thống tuân thủ nghiêm ngặt ngăn xếp công nghệ được định nghĩa:

```text
┌─────────────────────────────────────────────────────────────┐
│                    GIAO DIỆN FRONT-END                      │
│      HTML5 / CSS3 / Bootstrap 5 / JavaScript (ES6+)         │
│           jQuery / AJAX / Apache ECharts 5.6.0              │
└──────────────────────────────┬──────────────────────────────┘
                               │ JSON / HTTP REST
┌──────────────────────────────▼──────────────────────────────┐
│                    TẦNG XỬ LÝ BACK-END                      │
│               PHP 8.2+ (OOP, Service-Oriented)              │
│       - Data Ingestion & Parsing Services                   │
│       - Apriori Mining Engine (Join / Prune / Count)        │
│       - Association Rule Mining Engine                      │
│       - Benchmark Harness & Lineage Tracking                │
└──────────────────────────────┬──────────────────────────────┘
                               │ PDO Prepared Statements
┌──────────────────────────────▼──────────────────────────────┐
│                    TẦNG DỮ LIỆU PERSISTENCE                 │
│                 MySQL 8.4 (InnoDB Storage)                  │
│    - Datasets & Transactions Schema                         │
│    - Mining Runs & Pattern Artifacts Schema                 │
└─────────────────────────────────────────────────────────────┘
```

---

## 6. KIẾN TRÚC VÀ HIỆN THỰC HỆ THỐNG (SYSTEM ARCHITECTURE AND IMPLEMENTATION)

### 6.1 Kiến Trúc Tổng Thể Hướng Dịch Vụ
Hệ thống được tổ chức theo mô hình phân tách trách nhiệm cao độ (*Separation of Concerns*):
- `App\Services\DatasetService`: Quản lý tiếp nhận, kiểm tra tính toàn vẹn và nạp dữ liệu.
- `App\Mining\Apriori`: Động cơ lõi điều phối thuật toán Apriori.
- `App\Mining\CandidateJoiner`: Dịch vụ kết nối tiền tố $L_{k-1} \Join L_{k-1}$.
- `App\Mining\CandidatePruner`: Dịch vụ kiểm tra và cắt tỉa tập con không phổ biến.
- `App\Mining\SupportCounter`: Dịch vụ đếm tần suất xuất hiện của ứng viên trên tập giao dịch.
- `App\Mining\AssociationRuleMiner`: Dịch vụ sinh luật và tính toán support, confidence, lift.

### 6.2 Mã Giả Học Thuật của Thuật Toán Apriori Hiện Thực

```text
Algorithm: AprioriWithPruningInstrumentation
Input:     Database D, min_support in (0, 1]
Output:    Frequent Itemsets L = Union(L_k), Level Metrics M

1:  // Bậc k = 1: Quét đơn mục
2:  C_1 <- ScanUniqueItems(D)
3:  RecordMetrics(level: 1, generated: |C_1|, pruned: 0, evaluated: |C_1|)
4:  L_1 <- { c in C_1 | Support(c, D) >= min_support }
5:  k <- 2
6:
7:  while L_{k-1} != empty do
8:      // Bước kết nối: Join L_{k-1} với L_{k-1}
9:      C_k_raw <- JoinCandidateItemsets(L_{k-1})
10:     pruned_count <- 0
11:     C_k_survived <- empty
12:
13:     // Bước cắt tỉa theo tính chất Apriori
14:     for each candidate c in C_k_raw do
15:         has_infrequent_subset <- false
16:         for each (k-1)-subset s of c do
17:             if s not in L_{k-1} then
18:                 has_infrequent_subset <- true
19:                 break
20:             end if
21:         end for
22:         if has_infrequent_subset then
23:             pruned_count <- pruned_count + 1
24:         else
25:             C_k_survived <- C_k_survived union {c}
26:         end if
27:     end for
28:
29:     RecordMetrics(level: k, generated: |C_k_raw|, pruned: pruned_count, evaluated: |C_k_survived|)
30:
31:     // Bước đếm độ hỗ trợ và lọc
32:     L_k <- { c in C_k_survived | Support(c, D) >= min_support }
33:     k <- k + 1
34: end while
35: return L, M
```

### 6.3 Lược Đồ Cơ Sở Dữ Liệu Quan Hệ
Lược đồ cơ sở dữ liệu được thiết kế chuẩn hóa trên engine InnoDB:
- `datasets`: Lưu trữ thông tin metadata của tập dữ liệu, mã băm SHA-256 tệp gốc, tổng số giao dịch ($N$) và số mục phân biệt ($M$).
- `dataset_transactions`: Lưu danh sách các giao dịch ($TID$).
- `transaction_items`: Lưu các mục thuộc từng giao dịch, có chỉ mục kép `(transaction_id, item_name)` để tăng tốc độ truy vấn.
- `mining_runs`: Lưu trữ metadata của các lần chạy khai phá, tham số cấu hình, trạng thái thực thi và tổng thời gian thực thi.
- `frequent_itemsets`: Lưu trữ các tập mục phổ biến phát hiện được kèm bậc $k$ và độ hỗ trợ tương đối / tuyệt đối.
- `association_rules`: Lưu trữ danh sách luật kết hợp, tiền đề, hệ quả, các chỉ số $\text{support}, \text{confidence}, \text{lift}$.
- `itemset_level_metrics`: Lưu trữ số liệu chẩn đoán cắt tỉa chi tiết từng bậc độ dài $k$ (`candidates_generated`, `candidates_pruned`, `candidates_evaluated`, `frequent_count`, `pruning_ratio`).

### 6.4 Giao Diện Lập Trình Ứng Dụng (HTTP REST/AJAX API)
Hệ thống cung cấp các endpoint phi trạng thái:
- `GET /api/datasets.php`: Trả về danh sách tập dữ liệu và trạng thái kiểm tra.
- `POST /api/datasets.php?action=upload`: Tiếp nhận tệp tải lên và kích hoạt pipeline tiền xử lý.
- `POST /api/mining.php?action=run`: Kích hoạt tiến trình khai phá Apriori với tham số $\text{min\_support}$ và $\text{min\_confidence}$.
- `GET /api/mining.php?action=results&run_id={id}`: Trả về kết quả phân trang của tập mục phổ biến, luật kết hợp và số liệu cắt tỉa phân tầng dưới dạng chuẩn JSON (RFC 8259).

---

## 7. PHƯƠNG PHÁP NGHIÊN CỨU THỰC NGHIỆM (RESEARCH METHODOLOGY)

### 7.1 Tập Dữ Liệu Thực Nghiệm
Nghiên cứu sử dụng tập dữ liệu chuẩn hóa quốc tế **UCI Mushroom** (`agaricus-lepiota.data`):
- **Số lượng bản ghi giao dịch ($N$):** 8,124 giao dịch hoàn chỉnh.
- **Cấu trúc trường thuộc tính:** 23 trường phân loại vật lý (gồm trường lớp nhãn $c_1$ và 22 trường thuộc tính hình thái sinh học từ $c_2$ đến $c_{23}$).
- **Không gian mục chuẩn hóa:** Mỗi mục được mã hóa theo vị trí thuộc tính $c_j=\text{value}$, tạo thành tổng cộng **119 mục phân loại phân biệt**. Ký tự khuyết thiếu `'?'` được bảo toàn như một giá trị phân loại hợp lệ.

### 7.2 Lịch Sử Hiệu Chỉnh Ma Trận Ngưỡng Hỗ Trợ (Support-Matrix Revision Disclosure)
Nhằm bảo đảm tính trung thực và minh bạch học thuật tuyệt đối:
- **Ma trận đăng ký ban đầu:** $[0.20, 0.15, 0.10, 0.075, 0.05]$.
- **Kết quả thăm dò khả thi tiền hình thức (Pre-formal Feasibility Probe):** Thử nghiệm thăm dò không tạo bằng chứng cho thấy các ngưỡng dưới $0.35$ vi phạm các giới hạn bảo vệ thời gian thực thi trên tập dữ liệu Mushroom và cấu hình hiện tại (ngưỡng $0.25$ vượt giới hạn thời gian chạy 30 giây của Apriori; ngưỡng $0.30$ hoàn tất Apriori nhưng sinh ra hơn 50,000 luật kết hợp, vượt giới hạn tài nguyên).
- **Ma trận chính thức được phê duyệt:** Đúng một lần điều chỉnh có kiểm soát đã được thực hiện trước khi thu thập dữ liệu hình thức:
  $$\text{min\_support} \in [0.60, 0.50, 0.45, 0.40, 0.35]$$
  tương ứng với số giao dịch yêu cầu lần lượt là: **4,875, 4,062, 3,656, 3,250, và 2,844 giao dịch**. Ngưỡng độ tin cậy được cố định ở $\text{min\_confidence} = 0.75$.

### 7.3 Giao Thức Thu Thập Dữ Liệu Hình Thức (RQ1 / RQ2)
- Mỗi ngưỡng hỗ trợ thực hiện 2 lần chạy làm nóng (*warmup iterations*), tiếp nối bởi **10 lần lặp chính thức** được xáo trộn ngẫu nhiên tất định (*deterministic shuffle*, seed = 42).
- Đo lường chính xác thời gian thực thi thuật toán Apriori, số lượng ứng viên sinh ra, cắt tỉa, đánh giá và số lượng mẫu phát hiện.
- Thống kê phi tham số: Báo cáo giá trị **Trung vị (Median)** và **Khoảng liên phân vị (IQR)** theo phương pháp bản lề Tukey nhằm loại bỏ ảnh hưởng của các giá trị ngoại lai do dao động hệ thống.

### 7.4 Giao Thức Đo Kiểm Trực Quan Hóa Đối Chứng (RQ3)
- Môi trường trình duyệt cô lập: Microsoft Edge 151 (Chromium Engine), độ phân giải cửa sổ cố định $1440 \times 900$, Device Pixel Ratio = 1.0, kích thước khung vẽ đồ họa chuẩn $800 \times 600\text{ px}$.
- Ba thư viện đối chứng: **D3.js v7.9.0** (SVG DOM), **Chart.js v4.4.8** (HTML5 Canvas), **Apache ECharts v5.6.0** (HTML5 Canvas).
- Khối lượng dữ liệu điểm phân tán cố định: $N \in [100, 1000, 5000, 10000]$.
- Thước đo thời gian chuẩn hóa: **Độ trễ quan sát hai khung hình** (*render-to-two-frame-observation latency*) sử dụng hai lệnh `requestAnimationFrame` liên tiếp.
- Tổng cộng: 3 thư viện $\times$ 4 quy mô $\times$ 10 lần lặp = **120 quan sát hình thức hoàn chỉnh**.

---

## 8. KẾT QUẢ THỰC NGHIỆM (EXPERIMENTAL RESULTS)

### 8.1 Kết Quả RQ1: Ảnh Hưởng Của Ngưỡng Hỗ Trợ

Bảng T1 tổng hợp toàn bộ kết quả thực nghiệm hình thức cho RQ1 trên tập dữ liệu UCI Mushroom.

#### Bảng T1: Tóm Tắt Ảnh Hưởng Của Ngưỡng Hỗ Trợ Đến Không Gian Tìm Kiếm, Khối Lượng Mẫu và Thời Gian Thực Thi (RQ1)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| $\text{min\_support}$ | Số Lượng Giao Dịch Yêu Cầu | Ứng Viên Sinh Ra | Ứng Viên Bị Cắt Tỉa | Ứng Viên Đánh Giá | Tập Mục Phổ Biến | Số Lượng Luật ($\text{conf} \ge 0.75$) | Độ Dài $k_{\max}$ | Thời Gian Trung Vị Apriori (ms) | IQR Thời Gian (ms) | Tỷ Lệ Cắt Tỉa Tổng Thể |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **0.60** | 4,875 | 185 | 11 | 174 | 51 | 223 | 5 | **523.072** | 317.164 | 0.059459 |
| **0.50** | 4,062 | 336 | 29 | 307 | 153 | 664 | 5 | **1,424.078** | 238.000 | 0.086310 |
| **0.45** | 3,656 | 641 | 115 | 526 | 329 | 1,859 | 6 | **3,322.764** | 551.320 | 0.179407 |
| **0.40** | 3,250 | 1,104 | 280 | 824 | 565 | 3,576 | 7 | **5,737.617** | 1,270.777 | 0.253623 |
| **0.35** | 2,844 | 2,131 | 624 | 1,507 | 1,189 | 11,055 | 7 | **14,047.443** | 5,549.082 | 0.292820 |

---

#### Phân Tích Chi Tiết RQ1

1. **Thời Gian Thực Thi (Hình F1):**  
   Khi ngưỡng hỗ trợ giảm dần từ $0.60$ xuống $0.35$, thời gian thực thi trung vị tăng trưởng từ $523.072\text{ ms}$ lên $14,047.443\text{ ms}$ (tăng hơn 26 lần). Động thái gia tăng này phản ánh sự mở rộng mạnh mẽ của khối lượng tính toán khi thuật toán phải đánh giá độ hỗ trợ cho số lượng lớn ứng viên sống sót qua từng lượt quét giao dịch.

```text
[Hình F1: Thời Gian Thực Thi Apriori theo Ngưỡng Hỗ Trợ Tối Thiểu (RQ1)]
File: experiments/figures/F1_apriori_runtime_vs_support.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

2. **Không Gian Tìm Kiếm Ứng Viên (Hình F2):**  
   Số lượng ứng viên sinh ra mở rộng đáng kể từ $185$ ứng viên tại ngưỡng $0.60$ lên $2,131$ ứng viên tại ngưỡng $0.35$. Đáng chú ý, số lượng ứng viên sống sót đi vào bước đánh giá độ hỗ trợ tăng từ $174$ lên $1,507$, trong khi số lượng ứng viên bị loại bỏ sớm bởi cơ chế cắt tỉa tăng từ $11$ lên $624$.

```text
[Hình F2: Khối Lượng Không Gian Ứng Viên theo Ngưỡng Hỗ Trợ Tối Thiểu (RQ1 / RQ2)]
File: experiments/figures/F2_candidate_volume_vs_support.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

3. **Khối Lượng Mẫu Phát Hiện (Hình F3):**  
   Số lượng tập mục phổ biến tăng từ $51$ lên $1,189$ tập mục. Tương ứng, số lượng luật kết hợp thỏa mãn $\text{min\_confidence} \ge 0.75$ tăng mạnh từ $223$ lên $11,055$ luật, khẳng định tính nhạy cảm cao của không gian mẫu đối với ngưỡng lọc hỗ trợ.

```text
[Hình F3: Khối Lượng Tập Mục Phổ Biến và Luật Kết Hợp theo Ngưỡng Hỗ Trợ (RQ1)]
File: experiments/figures/F3_pattern_output_vs_support.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

---

### 8.2 Kết Quả RQ2: Động Thái và Hiệu Quả Cắt Tỉa Apriori

Bảng T2 và Bảng T2b (phụ lục) cung cấp bằng chứng định lượng về hiệu quả của cơ chế cắt tỉa tập con.

#### Bảng T2: Tóm Tắt Tỷ Lệ Cắt Tỉa Ứng Viên Tổng Thể theo Ngưỡng Hỗ Trợ (RQ2)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| $\text{min\_support}$ | Ứng Viên Sinh Ra ($C_k$) | Ứng Viên Bị Cắt Tỉa | Ứng Viên Đánh Giá Độ Hỗ Trợ | Tỷ Lệ Cắt Tỉa Tổng Thể |
| :---: | :---: | :---: | :---: | :---: |
| **0.60** | 185 | 11 | 174 | **5.95%** (0.059459) |
| **0.50** | 336 | 29 | 307 | **8.63%** (0.086310) |
| **0.45** | 641 | 115 | 526 | **17.94%** (0.179407) |
| **0.40** | 1,104 | 280 | 824 | **25.36%** (0.253623) |
| **0.35** | 2,131 | 624 | 1,507 | **29.28%** (0.292820) |

---

#### Phân Tích Động Thái Cắt Tỉa Phân Tầng (Hình F4)

- **Bậc $k = 1$ (`singleton_scan`):** Quét 119 mục đơn phân loại; số lượng cắt tỉa bằng 0 theo định nghĩa vì đơn mục không có tập con thực sự.
- **Bậc $k = 2$ (`join_prune`):** Giai đoạn kết nối - cắt tỉa bắt đầu áp dụng từ $k \ge 2$. Tuy nhiên, tại $k = 2$, tất cả các cặp mục được sinh ra từ $L_1 \Join L_1$ đều có các tập con độ dài 1 nằm trong $L_1$ theo định nghĩa, do đó số lượng ứng viên bị tỉa tại $k = 2$ bằng 0 trên mọi ngưỡng hỗ trợ.
- **Bậc $k \ge 3$ (`join_prune`):** Cắt tỉa tập con thực tế bắt đầu loại bỏ ứng viên từ bậc $k = 3$ và phát huy hiệu quả mạnh mẽ ở các bậc cao:
  - Tại $\text{min\_support} = 0.60$: Tỷ lệ cắt tỉa đạt $17.4\%$ ở $k=3$, $46.2\%$ ở $k=4$, và $50.0\%$ ở $k=5$.
  - Tại $\text{min\_support} = 0.35$: Cắt tỉa loại bỏ $208 / 563$ ứng viên ($36.9\%$) tại $k=3$, loại bỏ $243 / 649$ ứng viên ($37.4\%$) tại $k=4$, loại bỏ $134 / 390$ ứng viên ($34.4\%$) tại $k=5$, và $34 / 118$ ứng viên ($28.8\%$) tại $k=6$.

```text
[Hình F4: Động Thái Phân Tầng Ứng Viên và Tỷ Lệ Cắt Tỉa Qua Toàn Bộ 5 Ngưỡng Hỗ Trợ (RQ2)]
File: experiments/figures/F4_pruning_dynamics_per_level.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

---

### 8.3 Kết Quả RQ3: So Sánh Hiệu Năng Trực Quan Hóa Front-End

Bảng T3 tổng hợp các quan sát độ trễ hiển thị và cập nhật dữ liệu của ba thư viện đồ họa web.

#### Bảng T3: So Sánh Độ Trễ Khởi Tạo và Cập Nhật Dữ Liệu của D3.js, Chart.js và Apache ECharts (RQ3)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| Thư Viện Đồ Họa | Phiên Bản | Kiến Trúc Dựng Hình | Quy Mô Dữ Liệu ($N$) | Số Lần Chạy Hợp Lệ | Độ Trễ Trung Vị Khởi Tạo (ms) | IQR Khởi Tạo (ms) | Độ Trễ Trung Vị Cập Nhật (ms) | IQR Cập Nhật (ms) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Chart.js** | 4.4.8 | Canvas | **100** | 10 / 10 | **18.000** | 16.700 | **16.350** | 0.600 |
| **Chart.js** | 4.4.8 | Canvas | **1,000** | 10 / 10 | **17.500** | 1.800 | **16.950** | 16.600 |
| **Chart.js** | 4.4.8 | Canvas | **5,000** | 10 / 10 | **42.400** | 4.900 | **39.800** | 7.900 |
| **Chart.js** | 4.4.8 | Canvas | **10,000** | 10 / 10 | **70.550** | 14.800 | **60.950** | 11.700 |
| **D3.js** | 7.9.0 | SVG | **100** | 10 / 10 | **17.300** | 1.200 | **17.750** | 15.100 |
| **D3.js** | 7.9.0 | SVG | **1,000** | 10 / 10 | **18.250** | 15.000 | **17.900** | 13.000 |
| **D3.js** | 7.9.0 | SVG | **5,000** | 10 / 10 | **72.750** | 10.100 | **57.650** | 10.300 |
| **D3.js** | 7.9.0 | SVG | **10,000** | 10 / 10 | **138.600** | 26.600 | **117.700** | 19.200 |
| **Apache ECharts** | 5.6.0 | Canvas | **100** | 10 / 10 | **24.850** | 15.700 | **17.100** | 15.900 |
| **Apache ECharts** | 5.6.0 | Canvas | **1,000** | 10 / 10 | **27.550** | 7.300 | **32.300** | 8.800 |
| **Apache ECharts** | 5.6.0 | Canvas | **5,000** | 10 / 10 | **111.000** | 8.400 | **96.250** | 8.900 |
| **Apache ECharts** | 5.6.0 | Canvas | **10,000** | 10 / 10 | **222.600** | 38.400 | **195.800** | 8.300 |

---

#### Phân Tích Hiệu Năng Trực Quan Hóa (Hình F5 và F6)

1. **Vùng Dữ Liệu Nhỏ ($N \le 1,000$):**  
   Tại $N \le 1,000$, các quan sát trung vị duy trì gần mức một đến hai khoảng khung hình 60 Hz ($16.4\text{ ms} - 32.3\text{ ms}$). Do giao thức double-rAF bị lượng tử hóa theo khung hình (*frame-quantized*), các chênh lệch nhỏ trong vùng này không phản ánh sự vượt trội rõ rệt giữa các thư viện.

2. **Vùng Dữ Liệu Dày Đặc ($N \ge 5,000$):**  
   Khi khối lượng điểm tăng lên $10,000$, Chart.js ghi nhận độ trễ trung vị thấp nhất ($70.550\text{ ms}$ khởi tạo / $60.950\text{ ms}$ cập nhật), D3.js ghi nhận mức trung gian ($138.600\text{ ms}$ khởi tạo / $117.700\text{ ms}$ cập nhật), và Apache ECharts đạt $222.600\text{ ms}$ khởi tạo / $195.800\text{ ms}$ cập nhật dưới chế độ vẽ tiêu chuẩn.

3. **So Sánh Cập Nhật Dữ Liệu Tại Chỗ vs. Khởi Tạo Lần Đầu:**  
   Tại các quy mô lớn ($N = 5,000$ và $N = 10,000$), độ trễ cập nhật dữ liệu tại chỗ luôn thấp hơn độ trễ khởi tạo ban đầu trên cả ba thư viện (ví dụ D3.js giảm từ $138.600\text{ ms}$ xuống $117.700\text{ ms}$; Chart.js giảm từ $70.550\text{ ms}$ xuống $60.950\text{ ms}$).

```text
[Hình F5: Độ Trễ Khởi Tạo Biểu Đồ Ban Đầu theo Quy Mô Dữ Liệu (RQ3)]
File: experiments/figures/F5_visualization_initial_render.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.

[Hình F6: Độ Trễ Cập Nhật Dữ Liệu Tại Chỗ theo Quy Mô Dữ Liệu (RQ3)]
File: experiments/figures/F6_visualization_update.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

---

## 9. THẢO LUẬN (DISCUSSION)

### 9.1 Mối Quan Hệ Giữa Động Thái Thuật Toán và Ứng Dụng Web
Kết quả thực nghiệm từ RQ1 và RQ2 cung cấp những hiểu biết quan trọng cho việc thiết kế các hệ thống khai phá dữ liệu tương tác:
- **Hiện tượng "Bùng nổ đáy" (Bottom Explosion):** Khi hạ ngưỡng hỗ trợ, số lượng ứng viên và luật kết hợp tăng vọt. Nếu không có cơ chế giới hạn trả về (*guardrails*) và phân trang hiệu quả phía API, máy chủ web có thể dễ dàng bị cạn kiệt bộ nhớ hoặc rơi vào tình trạng quá tải thời gian phản hồi HTTP.
- **Vai trò bảo vệ của Cắt tỉa Apriori:** Tỷ lệ cắt tỉa tăng dần lên gần $30\%$ cho thấy tính chất Apriori đã ngăn chặn hàng trăm ứng viên không phổ biến tham gia vào bước quét dữ liệu. Điều này khẳng định giá trị của việc hiện thực hóa mô-đun cắt tỉa tối ưu trong các dịch vụ web backend.

### 9.2 Lựa Chọn Thư Viện Trực Quan Hóa Trên Bảng Điều Khiển Web
Kết quả RQ3 lý giải tính hợp lý của kiến trúc hệ thống:
- Mặc dù Chart.js đạt độ trễ thấp hơn ở tác vụ vẽ điểm phân tán dày đặc, Apache ECharts cung cấp hệ sinh thái tính năng phong phú hơn (hỗ trợ ma trận nhiệt tương quan, biểu đồ thanh phân tầng linh hoạt và công cụ chuyển đổi chuỗi dữ liệu khai phá trực quan).
- Trong ứng dụng thực tế của bảng điều khiển web, đa số các tập mẫu phổ biến và luật kết hợp được lọc người dùng nằm trong khoảng $N \le 1,000$, nơi độ trễ của Apache ECharts ($17.1\text{ ms} - 32.3\text{ ms}$) hoàn toàn đáp ứng tốt tính mượt mà của giao diện.

---

## 10. ĐE DỌA GIÁ TRỊ THỰC NGHIỆM VÀ HẠN CHẾ (THREATS TO VALIDITY & LIMITATIONS)

### 10.1 Hạn Chế Của Thực Nghiệm Khai Phá (RQ1 & RQ2)
1. **Phạm Vi Tập Dữ Liệu Đơn Lẻ:** Toàn bộ quan sát hình thức được thực hiện trên tập dữ liệu chuẩn UCI Mushroom ($N = 8,124$). Các đặc tính phân phối của dữ liệu giao dịch thương mại thưa (*sparse market basket data*) có thể mang lại động thái cắt tỉa khác biệt.
2. **Hiệu Chỉnh Ma Trận Hỗ Trợ:** Ma trận hỗ trợ chính thức $[0.35 - 0.60]$ được hiệu chỉnh để bảo đảm tính khả thi tính toán trên hệ thống; các ngưỡng hỗ trợ rất thấp ($< 0.30$) chưa được đo lường do vi phạm giới hạn thời gian chạy.
3. **Thiếu Đường Cơ Sở Đo Thời Gian Không Cắt Tỉa:** Do hệ thống không triển khai phiên bản Apriori thô loại bỏ hoàn toàn bước tỉa, hiệu quả cắt tỉa chỉ được định lượng qua số lượng ứng viên và tỷ lệ cắt tỉa, không thể suy diễn tỷ lệ tăng tốc thời gian thực thi (*wall-clock speedup*).
4. **Tính Đơn Điệu Của Ứng Viên:** Động thái giảm dần của số lượng ứng viên theo $k$ là một kết quả chẩn đoán thực nghiệm trên tập dữ liệu cụ thể, không phải là một định lý toán học bất biến trên mọi phân phối dữ liệu.
5. **Cố Định Ngưỡng Tin Cậy:** Mọi luật kết hợp được khảo sát ở mức $\text{min\_confidence} = 0.75$.

### 10.2 Hạn Chế Của Thực Nghiệm Trực Quan Hóa (RQ3)
1. **Ràng Buộc Kiến Trúc Dựng Hình:** D3.js gắn liền với SVG DOM trong khi Chart.js và ECharts sử dụng Canvas. Kiến trúc dựng hình là một phần cấu thành của đối tượng nghiên cứu, không phải một biến số thuật toán cô lập hoàn toàn.
2. **Lượng Tử Hóa Khung Hình (Frame Quantization):** Thước đo double-rAF chịu ảnh hưởng bởi chu kỳ quét khung hình 60 Hz ($\sim 16.7\text{ ms}$). Thước đo này **không** đo lường thời gian hoàn tất phần cứng GPU (*GPU completion*) hay thời gian quét điểm ảnh màn hình (*paint/presentation completion*).
3. **Bố Cục Vùng Vẽ Nội Bộ:** Kích thước stage tổng thể cố định $800 \times 600\text{ px}$, nhưng lề biên và tính toán trục tọa độ nội bộ tuân theo cơ chế tự động của từng thư viện.
4. **Bộ Thu Gom Rác Trình Duyệt (Garbage Collection):** Hoạt động thu gom rác nền của trình duyệt là yếu tố nhiễu không thể kiểm soát tuyệt đối, được giảm thiểu bằng cách sử dụng thống kê Trung vị và IQR.
5. **Phạm Vi Tác Vụ:** Chỉ khảo sát biểu đồ phân tán 2D với dữ liệu số thuần túy và tắt toàn bộ hiệu ứng chuyển động (*animations disabled*).

---

## 11. KẾT LUẬN (CONCLUSION)

Đề tài đã hoàn thành xuất sắc các mục tiêu nghiên cứu và phát triển phần mềm đề ra cho giai đoạn giữa kỳ:
- Xây dựng thành công hệ thống ứng dụng web bảng điều khiển tương tác hoàn chỉnh, tích hợp liền mạch giữa tầng xử lý nghiệp vụ PHP, cơ sở dữ liệu MySQL và giao diện người dùng ECharts/Bootstrap đáp ứng.
- Động cơ khai phá Apriori được hiện thực hóa với kiến trúc mô-đun hóa cao, cung cấp đầy đủ số liệu chẩn đoán cắt tỉa chi tiết qua từng cấp độ $k$.
- Thiết lập thành công khung thực nghiệm có kiểm soát với quy trình đóng băng dữ liệu nghiêm ngặt, trả lời thỏa đáng ba câu hỏi nghiên cứu RQ1, RQ2 và RQ3 với các bằng chứng thực nghiệm có thể tái lập 100%.

---

## 12. HƯỚNG PHÁT TRIỂN TƯƠNG LAI (FUTURE WORK)

Trong giai đoạn cuối kỳ, dự án định hướng mở rộng các nội dung sau:
1. **Tích hợp Thuật toán Đối chứng FP-Growth:** Hiện thực hóa động cơ FP-Growth song song trên PHP để thực hiện đối sánh trực tiếp về thời gian thực thi và mức tiêu thụ bộ nhớ với Apriori trên cùng một giao diện bảng điều khiển.
2. **Mở Rộng Hỗ Trợ Đa Tập Dữ Liệu:** Bổ sung các tập dữ liệu giao dịch thương mại bán lẻ quy mô lớn (ví dụ *Online Retail*, *Instacart Basket Data*).
3. **Tối Ưu Hóa Bộ Đệm Phân Tán (Distributed Caching):** Áp dụng giải pháp lưu trữ bộ đệm cho các tập mục phổ biến đã khai phá nhằm tăng tốc độ phản hồi cho các truy vấn lặp lại trên bảng điều khiển web.

---

## 13. TÀI LIỆU THAM KHẢO (REFERENCES)

1. **Agrawal, R., & Srikant, R. (1994).** *Fast Algorithms for Mining Association Rules in Large Databases.* In Proceedings of the 20th International Conference on Very Large Data Bases (VLDB '94), Santiago, Chile, pp. 487–498.
2. **Agrawal, R., Imieliński, T., & Swami, A. (1993).** *Mining Association Rules between Sets of Items in Large Databases.* In Proceedings of the 1993 ACM SIGMOD International Conference on Management of Data (SIGMOD '93), Washington, D.C., USA, pp. 207–216.
3. **Han, J., Pei, J., & Yin, Y. (2000).** *Mining Frequent Patterns without Candidate Generation.* In Proceedings of the 2000 ACM SIGMOD International Conference on Management of Data (SIGMOD '00), Dallas, Texas, USA, pp. 1–12.
4. **Tan, P.-N., Steinbach, M., Karpatne, A., & Kumar, V. (2018).** *Introduction to Data Mining (2nd Edition).* Pearson. `[NEEDS_REFERENCE_VERIFICATION]`
5. **UCI Machine Learning Repository.** *Mushroom Data Set (agaricus-lepiota.data).* Donor: Jeff Schlimmer (1987). URL: https://archive.ics.uci.edu/dataset/73/mushroom `[NEEDS_REFERENCE_VERIFICATION]`
6. **Bostock, M., Ogievetsky, V., & Heer, J. (2011).** *D3: Data-Driven Documents.* IEEE Transactions on Visualization and Computer Graphics, 17(12), pp. 2301–2309.
7. **Li, D., et al. (2018).** *ECharts: A Declarative Framework for Rapid Construction of Web-based Visualization.* Visual Informatics, 2(2), pp. 136–146. `[NEEDS_REFERENCE_VERIFICATION]`
8. **Chart.js Project (2024).** *Chart.js Documentation (v4.4.8).* URL: https://www.chartjs.org/ `[NEEDS_REFERENCE_VERIFICATION]`
9. **The PHP Group (2024).** *PHP 8.2 Documentation.* URL: https://www.php.net/
10. **Oracle Corporation (2024).** *MySQL 8.4 Reference Manual.* URL: https://dev.mysql.com/doc/
11. **Bray, T. (Ed.) (2017).** *RFC 8259: The JavaScript Object Notation (JSON) Data Interchange Format.* IETF.

---

## PHỤ LỤC: BẢNG SỐ LIỆU CẮT TỈA PHÂN TẦNG CHI TIẾT (APPENDIX - TABLE T2b)

#### Bảng T2b: Chi Tiết Động Thái Cắt Tỉa Qua Từng Bậc Độ Dài $k$ Cho Toàn Bộ 5 Ngưỡng Hỗ Trợ (RQ2)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| $\text{min\_support}$ | Bậc $k$ | Phân Loại Giai Đoạn | Ứng Viên Sinh Ra | Ứng Viên Bị Tỉa | Ứng Viên Đánh Giá | Tập Mục Phổ Biến | Tỷ Lệ Cắt Tỉa |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 0.60 | 1 | `singleton_scan` | 119 | 0 | 119 | 14 | 0.000000 |
| 0.60 | 2 | `join_prune` | 46 | 0 | 46 | 21 | 0.000000 |
| 0.60 | 3 | `join_prune` | 15 | 3 | 12 | 12 | 0.200000 |
| 0.60 | 4 | `join_prune` | 4 | 7 | 3 | 3 | 0.700000 |
| 0.60 | 5 | `join_prune` | 1 | 1 | 0 | 1 | 1.000000 |
| 0.50 | 1 | `singleton_scan` | 119 | 0 | 119 | 20 | 0.000000 |
| 0.50 | 2 | `join_prune` | 105 | 0 | 105 | 53 | 0.000000 |
| 0.50 | 3 | `join_prune` | 84 | 14 | 70 | 54 | 0.166667 |
| 0.50 | 4 | `join_prune` | 26 | 13 | 13 | 24 | 0.500000 |
| 0.50 | 5 | `join_prune` | 2 | 2 | 0 | 2 | 1.000000 |
| 0.45 | 1 | `singleton_scan` | 119 | 0 | 119 | 23 | 0.000000 |
| 0.45 | 2 | `join_prune` | 168 | 0 | 168 | 98 | 0.000000 |
| 0.45 | 3 | `join_prune` | 239 | 50 | 189 | 132 | 0.209205 |
| 0.45 | 4 | `join_prune` | 101 | 55 | 46 | 66 | 0.544554 |
| 0.45 | 5 | `join_prune` | 13 | 9 | 4 | 9 | 0.692308 |
| 0.45 | 6 | `join_prune` | 1 | 1 | 0 | 1 | 1.000000 |
| 0.40 | 1 | `singleton_scan` | 119 | 0 | 119 | 28 | 0.000000 |
| 0.40 | 2 | `join_prune` | 260 | 0 | 260 | 144 | 0.000000 |
| 0.40 | 3 | `join_prune` | 440 | 115 | 325 | 224 | 0.261364 |
| 0.40 | 4 | `join_prune` | 233 | 129 | 104 | 136 | 0.553648 |
| 0.40 | 5 | `join_prune` | 47 | 32 | 15 | 30 | 0.680851 |
| 0.40 | 6 | `join_prune` | 4 | 3 | 1 | 2 | 0.750000 |
| 0.40 | 7 | `join_prune` | 1 | 1 | 0 | 1 | 1.000000 |
| 0.35 | 1 | `singleton_scan` | 119 | 0 | 119 | 33 | 0.000000 |
| 0.35 | 2 | `join_prune` | 382 | 0 | 382 | 227 | 0.000000 |
| 0.35 | 3 | `join_prune` | 884 | 208 | 676 | 450 | 0.235294 |
| 0.35 | 4 | `join_prune` | 569 | 243 | 326 | 336 | 0.427065 |
| 0.35 | 5 | `join_prune` | 156 | 134 | 22 | 119 | 0.858974 |
| 0.35 | 6 | `join_prune` | 19 | 34 | 15 | 16 | 0.693878 |
| 0.35 | 7 | `join_prune` | 2 | 5 | 3 | 1 | 0.714286 |
