<form method="post" action="options.php">
    <?php
        settings_fields( 'autoblog_adv' );
    ?>
    <div class="card" style="max-width: 100%;">
        <h2>Advanced Agentic Features</h2>
        <p>Enable these features to transform Autoblog into a fully autonomous research and writing agent. Note that some features will increase API usage.</p>
        
        <table class="form-table">
            <!-- 0. Dynamic Search Agent -->
            <tr valign="top">
                <th scope="row">Dynamic Search Agent</th>
                <td>
                    <fieldset>
                        <label for="autoblog_enable_dynamic_search">
                            <input name="autoblog_enable_dynamic_search" type="checkbox" id="autoblog_enable_dynamic_search" value="1" <?php checked( '1', get_option( 'autoblog_enable_dynamic_search' ) ); ?> />
                            Enable Dynamic Search Queries
                        </label>
                    </fieldset>
                    <p class="description">
                        When enabled, the AI will use your base keywords as 'seeds' to generate specific, unique daily search queries (e.g., "Trends in AI" -> "Impact of AI in Healthcare 2025"). Works for Web Search sources.
                    </p>
                </td>
            </tr>

            <!-- 1. Deep Research -->
            <tr valign="top">
                <th scope="row">Deep Research Agent</th>
                <td>
                    <fieldset>
                        <label for="autoblog_enable_deep_research">
                            <input name="autoblog_enable_deep_research" type="checkbox" id="autoblog_enable_deep_research" value="1" <?php checked( '1', get_option( 'autoblog_enable_deep_research' ) ); ?> />
                            Enable Multi-Hop Research
                        </label>
                    </fieldset>
                    <p class="description">
                        If enabled, the agent will perform recursive research (Search -> Analyze -> Search) to gather facts before writing. 
                        <strong>Increases time per post by 1-2 minutes.</strong>
                    </p>
                </td>
            </tr>

            <!-- 2. Auto Interlinking -->
            <tr valign="top">
                <th scope="row">Autonomous Interlinking</th>
                <td>
                    <fieldset>
                        <label for="autoblog_enable_interlinking">
                            <input name="autoblog_enable_interlinking" type="checkbox" id="autoblog_enable_interlinking" value="1" <?php checked( '1', get_option( 'autoblog_enable_interlinking' ) ); ?> />
                            Enable Smart Internal Linking
                        </label>
                    </fieldset>
                    <p class="description">
                        Automatically finds relevant existing posts on your blog and inserts contextually appropriate internal links to boost SEO.
                    </p>
                </td>
            </tr>

            <!-- 3. Living Content -->
            <tr valign="top">
                <th scope="row">Living Content (Auto-Update)</th>
                <td>
                    <fieldset>
                        <label for="autoblog_enable_living_content">
                            <input name="autoblog_enable_living_content" type="checkbox" id="autoblog_enable_living_content" value="1" <?php checked( '1', get_option( 'autoblog_enable_living_content' ) ); ?> />
                            Enable Content Refresh
                        </label>
                    </fieldset>
                    <p class="description">
                        Periodically checks old posts for stale data (e.g., "Best of 2023") and rewrites them with fresh information without changing the URL.
                    </p>
                </td>
            </tr>

            <!-- 4. Multi-Modal -->
            <tr valign="top">
                <th scope="row">Multi-Modal Content</th>
                <td>
                    <fieldset>
                        <label for="autoblog_enable_multimodal">
                            <input name="autoblog_enable_multimodal" type="checkbox" id="autoblog_enable_multimodal" value="1" <?php checked( '1', get_option( 'autoblog_enable_multimodal' ) ); ?> />
                            Enable Charts & Embeds
                        </label>
                    </fieldset>
                    <p class="description">
                        Automatically generates data visualizations (Charts) for statistic-heavy articles and embeds relevant social media/video content.
                    </p>
                </td>
            </tr>

        </table>
    </div>
    <?php submit_button(); ?>
</form>

