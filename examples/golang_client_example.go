package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

// MissionAPIClient کلاینت Go برای API ماموریت‌ها
type MissionAPIClient struct {
	BaseURL string
	Token   string
	Client  *http.Client
}

// NewMissionAPIClient ایجاد کلاینت جدید
func NewMissionAPIClient(baseURL, token string) *MissionAPIClient {
	return &MissionAPIClient{
		BaseURL: baseURL,
		Token:   token,
		Client: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

// SetToken تنظیم توکن API
func (c *MissionAPIClient) SetToken(token string) {
	c.Token = token
}

// makeRequest ساخت و ارسال درخواست HTTP
func (c *MissionAPIClient) makeRequest(method, endpoint string, body interface{}) (*http.Response, error) {
	var reqBody io.Reader

	if body != nil {
		jsonData, err := json.Marshal(body)
		if err != nil {
			return nil, err
		}
		reqBody = bytes.NewBuffer(jsonData)
	}

	req, err := http.NewRequest(method, c.BaseURL+endpoint, reqBody)
	if err != nil {
		return nil, err
	}

	// اضافه کردن توکن
	if c.Token != "" {
		req.Header.Set("Authorization", "Bearer "+c.Token)
		req.Header.Set("X-API-Token", c.Token)
	}
	req.Header.Set("Content-Type", "application/json")

	return c.Client.Do(req)
}

// GetMetadata دریافت metadata
func (c *MissionAPIClient) GetMetadata() (map[string]interface{}, error) {
	resp, err := c.makeRequest("GET", "/metadata", nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

// TestToken تست توکن
func (c *MissionAPIClient) TestToken() (map[string]interface{}, error) {
	resp, err := c.makeRequest("GET", "/test-token", nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

// CreateMissionRequest درخواست ایجاد ماموریت
type CreateMissionRequest struct {
	Title         string `json:"title"`
	Description   string `json:"description,omitempty"`
	PromptContent string `json:"prompt_content"`
	ContentTitle  string `json:"content_title,omitempty"`
	ContentURL    string `json:"content_url,omitempty"`
	ContentType   string `json:"content_type,omitempty"`
	ContentDesc   string `json:"content_description,omitempty"`
	Points        int    `json:"points,omitempty"`
	Duration      int    `json:"duration,omitempty"`
	MaxPersonnel  int    `json:"max_personnel,omitempty"`
	AiID          int    `json:"ai_id,omitempty"`
	TenantID      int    `json:"tenant_id,omitempty"`
}

// CreateMission ایجاد ماموریت جدید
func (c *MissionAPIClient) CreateMission(req CreateMissionRequest) (map[string]interface{}, error) {
	resp, err := c.makeRequest("POST", "/missions", req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

// ListMissions دریافت لیست ماموریت‌ها
func (c *MissionAPIClient) ListMissions(page, perPage int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("/missions?page=%d&per_page=%d", page, perPage)
	resp, err := c.makeRequest("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

// GetMission دریافت یک ماموریت
func (c *MissionAPIClient) GetMission(missionID int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("/missions/%d", missionID)
	resp, err := c.makeRequest("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

// AddContentRequest درخواست اضافه کردن محتوا
type AddContentRequest struct {
	Title       string `json:"title"`
	ContentURL  string `json:"content_url,omitempty"`
	ContentType string `json:"content_type,omitempty"`
	Description string `json:"description,omitempty"`
	SortOrder   int    `json:"sort_order,omitempty"`
}

// AddContent اضافه کردن محتوای آموزشی
func (c *MissionAPIClient) AddContent(missionID int, req AddContentRequest) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("/missions/%d/content", missionID)
	resp, err := c.makeRequest("POST", endpoint, req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

// AssignMissionRequest درخواست اختصاص ماموریت
type AssignMissionRequest struct {
	PersonnelID int `json:"personnel_id"`
}

// AssignMission اختصاص ماموریت به پرسنل
func (c *MissionAPIClient) AssignMission(missionID, personnelID int) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("/missions/%d/assign", missionID)
	req := AssignMissionRequest{PersonnelID: personnelID}
	resp, err := c.makeRequest("POST", endpoint, req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

// SubmitResultRequest درخواست ارسال نتیجه
type SubmitResultRequest struct {
	ResultLink   string `json:"result_link"`
	PersonnelID  int    `json:"personnel_id"`
	SelectedAiID int    `json:"selected_ai_id,omitempty"`
}

// SubmitResult ارسال لینک نتیجه
func (c *MissionAPIClient) SubmitResult(missionID int, req SubmitResultRequest) (map[string]interface{}, error) {
	endpoint := fmt.Sprintf("/missions/%d/submit", missionID)
	resp, err := c.makeRequest("POST", endpoint, req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return result, nil
}

func main() {
	// تنظیمات
	baseURL := "https://your-domain.com/api/api/v1"
	token := "YOUR_TOKEN_HERE"

	// ایجاد کلاینت
	client := NewMissionAPIClient(baseURL, token)

	fmt.Println("=" + string(bytes.Repeat([]byte("="), 59)))
	fmt.Println("🚀 شروع استفاده از API ماموریت‌ها")
	fmt.Println("=" + string(bytes.Repeat([]byte("="), 59)))

	// 1. دریافت Metadata
	fmt.Println("\n1️⃣ دریافت Metadata...")
	metadata, err := client.GetMetadata()
	if err != nil {
		fmt.Printf("❌ خطا در دریافت Metadata: %v\n", err)
		return
	}

	fmt.Println("✅ Metadata دریافت شد")

	// استخراج اطلاعات
	data := metadata["data"].(map[string]interface{})
	tenants := data["tenants"].([]interface{})
	aiLmms := data["ai_llms"].([]interface{})

	fmt.Printf("   - تعداد Tenants: %d\n", len(tenants))
	fmt.Printf("   - تعداد AI/LLMs: %d\n", len(aiLmms))

	var tenantID float64
	var aiID float64

	if len(tenants) > 0 {
		tenant := tenants[0].(map[string]interface{})
		tenantID = tenant["id"].(float64)
		fmt.Printf("   - Tenant ID انتخابی: %.0f\n", tenantID)
	}

	if len(aiLmms) > 0 {
		ai := aiLmms[0].(map[string]interface{})
		aiID = ai["id"].(float64)
		aiName := ai["name"].(string)
		fmt.Printf("   - AI ID انتخابی: %.0f (%s)\n", aiID, aiName)
	}

	// 2. تست توکن
	fmt.Println("\n2️⃣ تست توکن...")
	tokenInfo, err := client.TestToken()
	if err != nil {
		fmt.Printf("❌ خطا در تست توکن: %v\n", err)
		return
	}

	tokenData := tokenInfo["data"].(map[string]interface{})
	fmt.Println("✅ توکن معتبر است")
	fmt.Printf("   - نوع: %v\n", tokenData["type"])
	fmt.Printf("   - Tenant ID: %v\n", tokenData["tenant_id"])

	// 3. ایجاد ماموریت
	fmt.Println("\n3️⃣ ایجاد ماموریت...")
	missionReq := CreateMissionRequest{
		Title:         "ماموریت تست از Go",
		PromptContent: "این پرامپت برای کپی کردن است",
		Points:        10,
		Description:   "توضیحات ماموریت تست",
	}

	if tenantID > 0 {
		missionReq.TenantID = int(tenantID)
	}
	if aiID > 0 {
		missionReq.AiID = int(aiID)
	}

	mission, err := client.CreateMission(missionReq)
	if err != nil {
		fmt.Printf("❌ خطا در ایجاد ماموریت: %v\n", err)
		return
	}

	missionData := mission["data"].(map[string]interface{})
	missionID := int(missionData["id"].(float64))
	fmt.Println("✅ ماموریت ایجاد شد")
	fmt.Printf("   - ID: %d\n", missionID)
	fmt.Printf("   - عنوان: %v\n", missionData["title"])

	// 4. اضافه کردن محتوا
	fmt.Println("\n4️⃣ اضافه کردن محتوا...")
	contentReq := AddContentRequest{
		Title:       "ویدیو آموزشی",
		ContentURL:  "https://example.com/video.mp4",
		ContentType: "video",
		Description: "توضیحات ویدیو",
	}

	_, err = client.AddContent(missionID, contentReq)
	if err != nil {
		fmt.Printf("❌ خطا در اضافه کردن محتوا: %v\n", err)
	} else {
		fmt.Println("✅ محتوا اضافه شد")
	}

	// 5. دریافت لیست ماموریت‌ها
	fmt.Println("\n5️⃣ دریافت لیست ماموریت‌ها...")
	missions, err := client.ListMissions(1, 10)
	if err != nil {
		fmt.Printf("❌ خطا در دریافت لیست: %v\n", err)
	} else {
		missionsData := missions["data"].(map[string]interface{})
		missionsList := missionsData["data"].([]interface{})
		fmt.Printf("✅ %d ماموریت دریافت شد\n", len(missionsList))
	}

	fmt.Println("\n" + "=" + string(bytes.Repeat([]byte("="), 59)))
	fmt.Println("✅ تمام عملیات با موفقیت انجام شد!")
	fmt.Println("=" + string(bytes.Repeat([]byte("="), 59)))
}
